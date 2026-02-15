<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SyncBoardGameFromBoardGameGeekJob;
use App\Models\BoardGame;
use App\Models\BggCollectionItem;
use App\Models\BggCollectionItemChange;
use App\Models\BggCollectionSync;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Syncs a user's BoardGameGeek collection via the BGG XML API.
 *
 * Fetches collection (with stats=1, version=1), resolves version IDs to base game IDs,
 * tracks changes (added/removed/rating), upserts collection items, and queues board game
 * sync for any missing games.
 */
class BoardGameGeekCollectionSyncService extends BaseService
{
    public function __construct(
        private readonly BoardGameGeekApiClient $apiClient,
    ) {
    }

    /**
     * Sync the BGG collection for the given user (async-safe; call from job).
     *
     * @throws \RuntimeException On API or parsing errors
     */
    public function syncCollectionForUser(User $user): void
    {
        $username = $user->board_game_geek_username;
        if ($username === null || $username === '') {
            Log::warning('Cannot sync BGG collection: user has no BGG username', ['user_id' => $user->id]);
            return;
        }

        $sync = BggCollectionSync::create([
            'user_id' => $user->id,
            'synced_at' => now(),
            'status' => BggCollectionSync::STATUS_PENDING,
        ]);

        try {
            $items = $this->apiClient->fetchCollection($username);
        } catch (\Throwable $e) {
            $sync->update([
                'status' => BggCollectionSync::STATUS_FAILED,
                'error_message' => substr($e->getMessage(), 0, 500),
            ]);
            Log::error('BGG collection fetch failed', [
                'user_id' => $user->id,
                'username' => $username,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }

        $objectIds = array_column($items, 'objectid');
        $baseGameIds = $this->apiClient->fetchBaseGameIdsForThingIds($objectIds);

        foreach ($items as $i => $item) {
            $items[$i]['bgg_base_game_id'] = $baseGameIds[$item['objectid']] ?? $item['objectid'];
        }

        $previousItems = $user->bggCollectionItems()->get()->keyBy('bgg_object_id');

        $this->recordChangesAndUpsertItems($user, $sync, $items, $previousItems);

        $missingBggIds = $this->collectMissingBoardGameBggIds($user, $items);
        foreach ($missingBggIds as $bggId) {
            SyncBoardGameFromBoardGameGeekJob::dispatch($bggId)
                ->delay(now()->addSeconds(2));
        }

        $sync->update([
            'status' => BggCollectionSync::STATUS_SUCCESS,
            'items_count' => count($items),
            'error_message' => null,
        ]);

        Log::info('BGG collection sync completed', [
            'user_id' => $user->id,
            'username' => $username,
            'items_count' => count($items),
            'missing_games_queued' => count($missingBggIds),
        ]);
    }

    /**
     * Apply collection items from a pre-fetched source (e.g. JSON file) without calling the BGG API.
     *
     * Items must already include bgg_base_game_id. Use this when syncing from personal folder files.
     *
     * @param User $user The user to sync the collection for
     * @param array<int, array<string, mixed>> $items Parsed collection items (same shape as fetchCollection), with bgg_base_game_id set
     * @return void
     */
    public function syncCollectionForUserFromItems(User $user, array $items): void
    {
        $username = $user->board_game_geek_username;
        if ($username === null || $username === '') {
            Log::warning('Cannot sync BGG collection from items: user has no BGG username', ['user_id' => $user->id]);
            return;
        }

        $sync = BggCollectionSync::create([
            'user_id' => $user->id,
            'synced_at' => now(),
            'status' => BggCollectionSync::STATUS_PENDING,
        ]);

        foreach ($items as $i => $item) {
            if (!isset($item['bgg_base_game_id']) || $item['bgg_base_game_id'] === null) {
                $items[$i]['bgg_base_game_id'] = $item['objectid'] ?? '';
            }
        }

        $previousItems = $user->bggCollectionItems()->get()->keyBy('bgg_object_id');
        $this->recordChangesAndUpsertItems($user, $sync, $items, $previousItems);

        $missingBggIds = $this->collectMissingBoardGameBggIds($user, $items);
        foreach ($missingBggIds as $bggId) {
            SyncBoardGameFromBoardGameGeekJob::dispatch($bggId)
                ->delay(now()->addSeconds(2));
        }

        $sync->update([
            'status' => BggCollectionSync::STATUS_SUCCESS,
            'items_count' => count($items),
            'error_message' => null,
        ]);

        Log::info('BGG collection sync from items completed', [
            'user_id' => $user->id,
            'username' => $username,
            'items_count' => count($items),
            'missing_games_queued' => count($missingBggIds),
        ]);
    }

    /**
     * Record added/removed/rating changes and upsert current collection items.
     *
     * @param array<int, array{objectid: string, name: string, yearpublished: ?int, thumbnail: ?string, user_rating: ?float, owned: bool, bgg_base_game_id: string}> $items
     */
    private function recordChangesAndUpsertItems(
        User $user,
        BggCollectionSync $sync,
        array $items,
        \Illuminate\Support\Collection $previousItems
    ): void {
        $newByObjectId = collect($items)->keyBy('objectid');

        foreach ($items as $item) {
            $objectId = $item['objectid'];
            $baseId = $item['bgg_base_game_id'];
            $previous = $previousItems->get($objectId);

            if ($previous === null) {
                $this->createChange($user, $sync, BggCollectionItemChange::CHANGE_TYPE_ADDED, $baseId, $objectId, null, [
                    'name' => $item['name'],
                    'user_rating' => $item['user_rating'],
                ]);
            } elseif ($this->ratingChanged($previous->user_rating, $item['user_rating'] ?? null)) {
                $this->createChange($user, $sync, BggCollectionItemChange::CHANGE_TYPE_RATING_CHANGED, $baseId, $objectId, [
                    'user_rating' => $previous->user_rating?->__toString(),
                ], [
                    'user_rating' => $item['user_rating'],
                ]);
            }

            $boardGameId = BoardGame::where('bgg_id', $baseId)->value('id');
            $isVersion = $objectId !== $baseId;

            BggCollectionItem::updateOrCreate(
                [
                    'user_id' => $user->id,
                    'bgg_object_id' => $objectId,
                ],
                [
                    'bgg_id' => $baseId,
                    'bgg_version_id' => $isVersion ? $objectId : null,
                    'bgg_collid' => $item['bgg_collid'] ?? null,
                    'board_game_id' => $boardGameId,
                    'name' => $item['name'],
                    'year_published' => $item['yearpublished'],
                    'thumbnail_url' => $item['thumbnail'] ?? null,
                    'image_url' => $item['image'] ?? null,
                    'user_rating' => $item['user_rating'],
                    'owned' => $item['owned'] ?? true,
                    'numplays' => $item['numplays'] ?? 0,
                    'prev_owned' => $item['prev_owned'] ?? false,
                    'for_trade' => $item['for_trade'] ?? false,
                    'want' => $item['want'] ?? false,
                    'want_to_play' => $item['want_to_play'] ?? false,
                    'want_to_buy' => $item['want_to_buy'] ?? false,
                    'wishlist' => $item['wishlist'] ?? false,
                    'preordered' => $item['preordered'] ?? false,
                    'last_synced_at' => now(),
                    'bgg_last_modified' => isset($item['bgg_last_modified']) && $item['bgg_last_modified'] !== ''
                        ? $item['bgg_last_modified']
                        : null,
                ]
            );
        }

        foreach ($previousItems as $objectId => $previous) {
            if (!$newByObjectId->has($objectId)) {
                $this->createChange($user, $sync, BggCollectionItemChange::CHANGE_TYPE_REMOVED, $previous->bgg_id, $objectId, [
                    'name' => $previous->name,
                ], null);
            }
        }

        $currentObjectIds = $newByObjectId->keys();
        $user->bggCollectionItems()->whereNotIn('bgg_object_id', $currentObjectIds)->delete();
    }

    private function ratingChanged(mixed $previous, mixed $current): bool
    {
        $a = $previous === null ? null : (float) $previous;
        $b = $current === null ? null : (float) $current;
        return $a !== $b;
    }

    private function createChange(
        User $user,
        BggCollectionSync $sync,
        string $changeType,
        string $bggId,
        string $bggObjectId,
        ?array $oldValue,
        ?array $newValue
    ): void {
        BggCollectionItemChange::create([
            'user_id' => $user->id,
            'bgg_collection_sync_id' => $sync->id,
            'change_type' => $changeType,
            'bgg_id' => $bggId,
            'bgg_object_id' => $bggObjectId,
            'old_value' => $oldValue,
            'new_value' => $newValue,
        ]);
    }

    /**
     * Collect unique base BGG IDs that do not yet have a BoardGame record.
     *
     * @param array<int, array{bgg_base_game_id: string}> $items
     * @return array<int, string>
     */
    private function collectMissingBoardGameBggIds(User $user, array $items): array
    {
        $baseIds = array_unique(array_column($items, 'bgg_base_game_id'));
        $existing = BoardGame::whereIn('bgg_id', $baseIds)->pluck('bgg_id')->all();
        return array_values(array_diff($baseIds, $existing));
    }
}
