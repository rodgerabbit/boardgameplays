<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BoardGame;
use App\Models\BggCollectionItem;
use App\Models\BggCollectionItemChange;
use App\Models\BggCollectionSync;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Syncs a user's BoardGameGeek collection via the BGG XML API.
 *
 * Two-phase flow: (1) Fetch collection, scan for missing board games, schedule batch syncs
 * and cache data; (2) After board games are synced, import phase runs and upserts from cache.
 */
class BoardGameGeekCollectionSyncService extends BaseService
{
    private const CACHE_KEY_PREFIX = 'bgg_collection_pending_';

    private const CACHE_KEY_BASE_IDS_SUFFIX = '_base_ids';

    public function __construct(
        private readonly BoardGameGeekApiClient $apiClient,
    ) {
    }

    /**
     * Run collection sync for the given user.
     *
     * When isImportPhase is false: fetches collection only, caches items, and returns chunk info
     * so the job can dispatch SyncCollectionThingIdsChunkJob per chunk. Chunk jobs resolve thing IDs
     * and the last chunk finalizes (upsert or schedule board game syncs + import phase).
     *
     * When isImportPhase is true: loads cached items, upserts collection, clears cache. Returns null.
     *
     * @return array{sync_id: int, chunk_thing_ids: array<int, array<string>>}|null Null when import phase completed
     * @throws \RuntimeException On API or parsing errors
     */
    public function syncCollectionForUser(User $user, bool $isImportPhase = false): ?array
    {
        if ($isImportPhase) {
            $this->importCollectionFromCache($user);
            return null;
        }

        return $this->fetchCollectionAndPrepareChunks($user);
    }

    /**
     * Fetch collection from BGG, create sync record, cache items (without base game IDs), and return thing-ID chunks for resolution in separate jobs.
     *
     * @return array{sync_id: int, chunk_thing_ids: array<int, array<string>>} Chunk arrays of thing IDs (max config max_ids_per_request per chunk)
     * @throws \RuntimeException On API or parsing errors
     */
    public function fetchCollectionAndPrepareChunks(User $user): array
    {
        $username = $user->board_game_geek_username;
        if ($username === null || $username === '') {
            Log::warning('Cannot sync BGG collection: user has no BGG username', ['user_id' => $user->id]);
            return ['sync_id' => 0, 'chunk_thing_ids' => []];
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
        if ($objectIds === []) {
            $sync->update([
                'status' => BggCollectionSync::STATUS_SUCCESS,
                'items_count' => 0,
                'error_message' => null,
            ]);
            Log::info('BGG collection sync completed (empty collection)', ['user_id' => $user->id, 'username' => $username]);
            return ['sync_id' => $sync->id, 'chunk_thing_ids' => []];
        }

        $chunkSize = (int) config('boardgamegeek.rate_limiting.max_ids_per_request', 20);
        $chunkSize = max(1, $chunkSize);
        $chunkThingIds = array_values(array_chunk($objectIds, $chunkSize));

        $ttlMinutes = (int) config('boardgamegeek.pending_import_cache_ttl_minutes', 60);
        $mainKey = self::CACHE_KEY_PREFIX . $user->id;
        Cache::put($mainKey, [
            'items' => $items,
            'sync_id' => $sync->id,
        ], now()->addMinutes($ttlMinutes));

        Cache::put(self::baseIdsCacheKey($user->id), [], now()->addMinutes($ttlMinutes));

        Log::info('BGG collection fetched; thing-ID resolution will run in chunk jobs', [
            'user_id' => $user->id,
            'username' => $username,
            'items_count' => count($items),
            'chunks_count' => count($chunkThingIds),
        ]);

        return [
            'sync_id' => $sync->id,
            'chunk_thing_ids' => $chunkThingIds,
        ];
    }

    /**
     * Merge resolved thing ID -> base game ID map into the cached base IDs for this user. Call from each chunk job.
     *
     * @param array<string, string> $thingIdToBaseId Map of thingId => baseGameBggId
     */
    public function mergeResolvedBaseGameIds(User $user, array $thingIdToBaseId): void
    {
        $key = self::baseIdsCacheKey($user->id);
        $existing = Cache::get($key);
        $merged = is_array($existing) ? $existing : [];
        foreach ($thingIdToBaseId as $thingId => $baseId) {
            $merged[(string) $thingId] = (string) $baseId;
        }
        $ttlMinutes = (int) config('boardgamegeek.pending_import_cache_ttl_minutes', 60);
        Cache::put($key, $merged, now()->addMinutes($ttlMinutes));
    }

    /**
     * After all chunk jobs have merged their base IDs, load items + base IDs, attach base IDs to items, then complete sync or schedule import. Call only from the last chunk job.
     *
     * @return array{missing_bgg_ids: array<string>, sync_id: int}|null Null when sync completed (no missing games); otherwise returns missing IDs and sync_id for the job to schedule batch syncs and import phase
     */
    public function finalizeCollectionSyncAfterChunks(User $user): ?array
    {
        $mainKey = self::CACHE_KEY_PREFIX . $user->id;
        $baseIdsKey = self::baseIdsCacheKey($user->id);
        $cached = Cache::get($mainKey);
        $baseIds = Cache::get($baseIdsKey);

        if ($cached === null || ! isset($cached['items'], $cached['sync_id']) || ! is_array($baseIds)) {
            Log::warning('BGG collection finalize: missing cache for user', ['user_id' => $user->id]);
            Cache::forget($mainKey);
            Cache::forget($baseIdsKey);
            return null;
        }

        $items = $cached['items'];
        $syncId = (int) $cached['sync_id'];
        $sync = BggCollectionSync::find($syncId);
        if ($sync === null || $sync->user_id !== $user->id) {
            Cache::forget($mainKey);
            Cache::forget($baseIdsKey);
            return null;
        }

        foreach ($items as $i => $item) {
            $objectId = $item['objectid'] ?? '';
            $items[$i]['bgg_base_game_id'] = $baseIds[$objectId] ?? $objectId;
        }

        $missingBggIds = $this->collectMissingBoardGameBggIds($user, $items);

        Cache::forget($baseIdsKey);

        if ($missingBggIds === []) {
            $previousItems = $user->bggCollectionItems()->get()->keyBy('bgg_object_id');
            $this->recordChangesAndUpsertItems($user, $sync, $items, $previousItems);
            $sync->update([
                'status' => BggCollectionSync::STATUS_SUCCESS,
                'items_count' => count($items),
                'error_message' => null,
            ]);
            Cache::forget($mainKey);
            Log::info('BGG collection sync completed (no missing games, chunked)', [
                'user_id' => $user->id,
                'items_count' => count($items),
            ]);
            return null;
        }

        $ttlMinutes = (int) config('boardgamegeek.pending_import_cache_ttl_minutes', 60);
        Cache::put($mainKey, [
            'items' => $items,
            'sync_id' => $sync->id,
        ], now()->addMinutes($ttlMinutes));

        Log::info('BGG collection finalize: missing games will be synced then import scheduled', [
            'user_id' => $user->id,
            'items_count' => count($items),
            'missing_bgg_ids_count' => count($missingBggIds),
        ]);

        return [
            'missing_bgg_ids' => $missingBggIds,
            'sync_id' => $sync->id,
        ];
    }

    private static function baseIdsCacheKey(int $userId): string
    {
        return self::CACHE_KEY_PREFIX . $userId . self::CACHE_KEY_BASE_IDS_SUFFIX;
    }

    /**
     * Import collection from cached items (phase 2). Call after board game batch syncs have run.
     */
    public function importCollectionFromCache(User $user): void
    {
        $key = self::CACHE_KEY_PREFIX . $user->id;
        $cached = Cache::get($key);
        if ($cached === null) {
            Log::warning('BGG collection import phase: no cached data found', ['user_id' => $user->id]);
            return;
        }

        $items = $cached['items'] ?? [];
        $syncId = (int) ($cached['sync_id'] ?? 0);
        if ($items === [] || $syncId === 0) {
            Cache::forget($key);
            return;
        }

        $sync = BggCollectionSync::find($syncId);
        if ($sync === null || $sync->user_id !== $user->id) {
            Cache::forget($key);
            return;
        }

        $previousItems = $user->bggCollectionItems()->get()->keyBy('bgg_object_id');
        $this->recordChangesAndUpsertItems($user, $sync, $items, $previousItems);
        $sync->update([
            'status' => BggCollectionSync::STATUS_SUCCESS,
            'items_count' => count($items),
            'error_message' => null,
        ]);
        Cache::forget($key);

        Log::info('BGG collection import phase completed', [
            'user_id' => $user->id,
            'items_count' => count($items),
        ]);
    }

    /**
     * Apply collection items from a pre-fetched source (e.g. JSON file) without calling the BGG API.
     *
     * Items must already include bgg_base_game_id. Missing board games are not synced here;
     * use the normal sync flow for that. This method upserts with whatever board_game_id exists.
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

        $sync->update([
            'status' => BggCollectionSync::STATUS_SUCCESS,
            'items_count' => count($items),
            'error_message' => null,
        ]);

        Log::info('BGG collection sync from items completed', [
            'user_id' => $user->id,
            'username' => $username,
            'items_count' => count($items),
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
