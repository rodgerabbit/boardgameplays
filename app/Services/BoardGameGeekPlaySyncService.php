<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BoardGame;
use App\Models\BoardGamePlay;
use App\Models\BoardGamePlayPlayer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

/**
 * Service class for syncing plays from BoardGameGeek.com.
 *
 * This service handles fetching plays from BGG XML API, parsing the XML,
 * and mapping the data to our database structure.
 */
class BoardGameGeekPlaySyncService extends BaseService
{
    private const PLAYS_API_URL = 'https://boardgamegeek.com/xmlapi2/plays';

    /**
     * Fetch plays from BoardGameGeek API for a single page.
     *
     * This method is used by queue jobs so that each job only performs one
     * HTTP request to BGG. Rate limiting between pages should be handled by
     * scheduling follow-up jobs with a delay rather than sleeping inside
     * the worker process.
     *
     * @param string $username The BGG username
     * @param string|null $minDate Minimum date (Y-m-d format)
     * @param string|null $maxDate Maximum date (Y-m-d format)
     * @param int $page Page number to fetch (1-based)
     * @return array{plays: array<int, SimpleXMLElement>, has_more_pages: bool}
     *
     * @throws \RuntimeException If API request fails
     */
    public function fetchPlaysPageFromBoardGameGeek(
        string $username,
        ?string $minDate,
        ?string $maxDate,
        int $page
    ): array {
        try {
            $url = self::PLAYS_API_URL . '?' . http_build_query([
                'username' => $username,
                'maxdate' => $maxDate,
                'mindate' => $minDate,
                'page' => $page,
            ]);

            $request = Http::timeout(30)
                ->retry(3, 1000)
                ->withHeaders([
                    'Accept' => 'application/xml',
                ]);

            $apiToken = config('boardgamegeek.api_token');
            if ($apiToken !== null) {
                $request->withToken($apiToken);
            }

            $response = $request->get($url);

            if (! $response->successful()) {
                if ($response->status() === 401) {
                    $errorMessage = 'BoardGameGeek API token was not accepted (401 Unauthorized). Please check BOARDGAMEGEEK_API_TOKEN in .env file.';
                    Log::error($errorMessage, [
                        'username' => $username,
                        'page' => $page,
                        'status' => $response->status(),
                    ]);
                    throw new \RuntimeException($errorMessage);
                }

                throw new \RuntimeException('HTTP request returned status code ' . $response->status());
            }

            $xml = new SimpleXMLElement($response->body());
            $plays = $xml->play ?? null;

            $playArray = [];
            if ($plays !== null) {
                foreach ($plays as $play) {
                    $playArray[] = $play;
                }
            }

            $playCount = count($playArray);

            return [
                'plays' => $playArray,
                'has_more_pages' => $playCount === 100,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to fetch plays from BGG', [
                'username' => $username,
                'page' => $page,
                'error' => $e->getMessage(),
            ]);

            throw new \RuntimeException(
                'Failed to fetch plays from BoardGameGeek: ' . $e->getMessage(),
                0,
                $e
            );
        }
    }

    /**
     * Fetch all plays from BoardGameGeek API with pagination.
     *
     * This method is retained for use outside of queued jobs where a
     * single long-running process is acceptable (e.g. CLI tooling
     * or tests). Queue jobs should prefer fetchPlaysPageFromBoardGameGeek().
     *
     * @param string $username The BGG username
     * @param string|null $minDate Minimum date (Y-m-d format)
     * @param string|null $maxDate Maximum date (Y-m-d format)
     * @return array<int, SimpleXMLElement> Array of XML play elements
     *
     * @throws \RuntimeException If API request fails
     */
    public function fetchPlaysFromBoardGameGeek(
        string $username,
        ?string $minDate = null,
        ?string $maxDate = null
    ): array {
        $allPlays = [];
        $page = 1;
        $hasMorePages = true;

        while ($hasMorePages) {
            $result = $this->fetchPlaysPageFromBoardGameGeek($username, $minDate, $maxDate, $page);
            $allPlays = array_merge($allPlays, $result['plays']);
            $hasMorePages = $result['has_more_pages'];
            $page++;

            if ($hasMorePages) {
                // Rate limiting when fetching all pages in-process.
                sleep(2);
            }
        }

        return $allPlays;
    }

    /**
     * Process BGG plays XML and sync to database.
     *
     * @param array<SimpleXMLElement> $plays Array of play XML elements
     * @param User $user The user to sync plays for
     * @return array<string> Array of BGG play IDs that were processed
     */
    public function processBggPlaysXml(array $plays, User $user): array
    {
        $processedPlayIds = [];

        foreach ($plays as $playElement) {
            try {
                if (!$this->validateBggPlay($playElement)) {
                    // Update existing play's is_incomplete when BGG marks it incomplete so deduplication excludes it
                    $incomplete = (int) ($playElement['incomplete'] ?? 0);
                    if ($incomplete !== 0) {
                        BoardGamePlay::where('bgg_play_id', (string) $playElement['id'])->update(['is_incomplete' => true]);
                    }
                    continue;
                }

                $play = $this->syncPlayFromBggXml($playElement, $user);
                if ($play !== null) {
                    $processedPlayIds[] = (string) $playElement['id'];
                }
                // When $play is null, the play was queued for import after board game sync (not skipped)
            } catch (\Exception $e) {
                Log::error('Failed to sync play from BGG', [
                    'user_id' => $user->id,
                    'bgg_play_id' => (string) ($playElement['id'] ?? 'unknown'),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $processedPlayIds;
    }

    /**
     * Sync a single play from BGG XML to database.
     *
     * If the board game is not yet in the database, queues the board game sync and a chained
     * play import job, and returns null. Otherwise creates the play and returns it.
     *
     * @param SimpleXMLElement $playElement The play XML element
     * @param User $user The user to sync for
     * @return BoardGamePlay|null The synced play, or null if import was deferred (board game queued for sync first)
     */
    public function syncPlayFromBggXml(SimpleXMLElement $playElement, User $user): ?BoardGamePlay
    {
        $bggPlayId = (string) $playElement['id'];
        $bggGameId = (string) $playElement->item[0]['objectid'];

        $boardGame = BoardGame::where('bgg_id', $bggGameId)->first();
        if ($boardGame === null) {
            return null;
        }

        // Ensure it's not an expansion
        if ($boardGame->is_expansion) {
            throw new \RuntimeException("Board game with BGG ID {$bggGameId} is an expansion, not a base game");
        }

        // Map play data
        $playData = $this->mapBggPlayToDatabase($playElement, $user, $boardGame->id);

        // Upsert play by bgg_play_id
        $play = BoardGamePlay::updateOrCreate(
            ['bgg_play_id' => $bggPlayId],
            array_merge($playData, [
                'bgg_synced_at' => now(),
                'bgg_sync_status' => 'synced',
            ])
        );

        // Delete existing players
        $play->players()->delete();

        // Sync players
        $players = $playElement->players[0]->player ?? null;
        if ($players !== null) {
            foreach ($players as $playerElement) {
                $playerData = $this->mapBggPlayerToDatabase($playerElement, $play->id);
                BoardGamePlayPlayer::create($playerData);
            }
        }

        // Sync expansions if any
        // Note: BGG XML doesn't directly indicate expansions used, so we skip this for now
        // Expansions would need to be inferred or manually added

        return $play->fresh(['boardGame', 'group', 'creator', 'players', 'expansions']);
    }

    /**
     * Return BGG game IDs from play payloads that do not yet have a BoardGame record.
     *
     * @param array<int, array<string, mixed>> $playPayloads Payloads from buildPlayPayloadFromBggXml
     * @return array<string> Unique BGG game IDs that are missing locally
     */
    public function getMissingBggGameIdsFromPayloads(array $playPayloads): array
    {
        $bggGameIds = [];
        foreach ($playPayloads as $payload) {
            $id = $payload['bgg_game_id'] ?? null;
            if ($id !== null && $id !== '') {
                $bggGameIds[(string) $id] = true;
            }
        }
        $bggGameIds = array_keys($bggGameIds);
        if ($bggGameIds === []) {
            return [];
        }
        $existing = BoardGame::whereIn('bgg_id', $bggGameIds)->pluck('bgg_id')->all();
        $existingSet = array_flip($existing);
        return array_values(array_filter($bggGameIds, fn (string $id) => ! isset($existingSet[$id])));
    }

    /**
     * Build a serializable play payload from BGG XML for deferred import (e.g. after board game sync).
     *
     * @param SimpleXMLElement $playElement The play XML element
     * @param User $user The user to sync for
     * @return array<string, mixed> Play payload (bgg_play_id, bgg_game_id, play fields, players)
     */
    public function buildPlayPayloadFromBggXml(SimpleXMLElement $playElement, User $user): array
    {
        $bggPlayId = (string) $playElement['id'];
        $bggGameId = (string) $playElement->item[0]['objectid'];
        $playedAt = (string) $playElement['date'];
        $location = (string) $playElement['location'];
        $comment = (string) ($playElement->comments ?? '');
        $length = (int) ($playElement['length'] ?? 0);
        $incomplete = (int) ($playElement['incomplete'] ?? 0);

        $players = [];
        $playerElements = $playElement->players[0]->player ?? null;
        if ($playerElements !== null) {
            foreach ($playerElements as $playerElement) {
                $players[] = $this->mapBggPlayerToPayload($playerElement);
            }
        }

        return [
            'bgg_play_id' => $bggPlayId,
            'bgg_game_id' => $bggGameId,
            'played_at' => $playedAt,
            'location' => $location !== '' ? $location : 'Unknown',
            'comment' => $comment !== '' ? $comment : null,
            'game_length_minutes' => $length > 0 ? $length : null,
            'is_incomplete' => $incomplete !== 0,
            'group_id' => $user->default_group_id,
            'created_by_user_id' => $user->id,
            'source' => 'boardgamegeek',
            'players' => $players,
        ];
    }

    /**
     * Create (or update) a play and its players from a serialized payload. Used by ImportBoardGamePlayFromBoardGameGeekJob.
     *
     * @param array<string, mixed> $playPayload Payload from buildPlayPayloadFromBggXml
     * @param User $user The user who owns the play
     * @param int $boardGameId The local board game ID (board game must already exist)
     * @return BoardGamePlay The created or updated play
     */
    public function createPlayFromPayload(array $playPayload, User $user, int $boardGameId): BoardGamePlay
    {
        $bggPlayId = (string) ($playPayload['bgg_play_id'] ?? '');
        $playData = [
            'board_game_id' => $boardGameId,
            'group_id' => $playPayload['group_id'] ?? $user->default_group_id,
            'created_by_user_id' => $user->id,
            'played_at' => $playPayload['played_at'] ?? now()->format('Y-m-d'),
            'location' => $playPayload['location'] ?? 'Unknown',
            'comment' => $playPayload['comment'] ?? null,
            'game_length_minutes' => $playPayload['game_length_minutes'] ?? null,
            'source' => 'boardgamegeek',
            'is_incomplete' => (bool) ($playPayload['is_incomplete'] ?? false),
            'bgg_play_id' => $bggPlayId,
            'bgg_synced_at' => now(),
            'bgg_sync_status' => 'synced',
        ];

        $play = BoardGamePlay::updateOrCreate(
            ['bgg_play_id' => $bggPlayId],
            $playData
        );

        $play->players()->delete();

        $playerPayloads = $playPayload['players'] ?? [];
        foreach ($playerPayloads as $playerPayload) {
            $playerData = array_merge($playerPayload, ['board_game_play_id' => $play->id]);
            BoardGamePlayPlayer::create($playerData);
        }

        return $play->fresh(['boardGame', 'group', 'creator', 'players', 'expansions']);
    }

    /**
     * Map BGG player XML to a serializable payload (no board_game_play_id). Used for deferred play import.
     *
     * @param SimpleXMLElement $playerElement The player XML element
     * @return array<string, mixed> Player data for payload
     */
    private function mapBggPlayerToPayload(SimpleXMLElement $playerElement): array
    {
        $username = (string) ($playerElement['username'] ?? '');
        $name = (string) ($playerElement['name'] ?? '');
        $win = (int) ($playerElement['win'] ?? 0);
        $new = (int) ($playerElement['new'] ?? 0);
        $score = (string) ($playerElement['score'] ?? '');
        $startPosition = (string) ($playerElement['startposition'] ?? '');

        $scoreValue = null;
        if ($score !== '') {
            $scoreValue = (float) str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', $score));
        }

        $playerData = [
            'is_winner' => $win === 1,
            'is_new_player' => $new === 1,
            'score' => $scoreValue,
            'position' => $startPosition !== '' ? (int) $startPosition : null,
        ];

        if ($username !== '') {
            $user = User::where('board_game_geek_username', $username)->first();
            if ($user !== null) {
                $playerData['user_id'] = $user->id;
                $playerData['board_game_geek_username'] = null;
                $playerData['guest_name'] = null;
            } else {
                $playerData['user_id'] = null;
                $playerData['board_game_geek_username'] = $username;
                $playerData['guest_name'] = null;
            }
        } else {
            $playerData['user_id'] = null;
            $playerData['board_game_geek_username'] = null;
            $playerData['guest_name'] = $name !== '' ? $name : 'Unknown';
        }

        return $playerData;
    }

    /**
     * Map BGG play data to our database format.
     *
     * @param SimpleXMLElement $playElement The play XML element
     * @param User $user The user
     * @param int $boardGameId The board game ID
     * @return array<string, mixed> Play data for database
     */
    public function mapBggPlayToDatabase(SimpleXMLElement $playElement, User $user, int $boardGameId): array
    {
        $bggPlayId = (string) $playElement['id'];
        $playedAt = (string) $playElement['date'];
        $location = (string) $playElement['location'];
        $comment = (string) ($playElement->comments ?? '');
        $length = (int) ($playElement['length'] ?? 0);
        $incomplete = (int) ($playElement['incomplete'] ?? 0);

        return [
            'board_game_id' => $boardGameId,
            'group_id' => $user->default_group_id,
            'created_by_user_id' => $user->id,
            'played_at' => $playedAt,
            'location' => $location !== '' ? $location : 'Unknown',
            'comment' => $comment !== '' ? $comment : null,
            'game_length_minutes' => $length > 0 ? $length : null,
            'source' => 'boardgamegeek',
            'is_incomplete' => $incomplete !== 0,
            'bgg_play_id' => $bggPlayId,
        ];
    }

    /**
     * Map BGG player data to our database format.
     *
     * @param SimpleXMLElement $playerElement The player XML element
     * @param int $playId The play ID
     * @return array<string, mixed> Player data for database
     */
    public function mapBggPlayerToDatabase(SimpleXMLElement $playerElement, int $playId): array
    {
        $username = (string) ($playerElement['username'] ?? '');
        $name = (string) ($playerElement['name'] ?? '');
        $win = (int) ($playerElement['win'] ?? 0);
        $new = (int) ($playerElement['new'] ?? 0);
        $score = (string) ($playerElement['score'] ?? '');
        $startPosition = (string) ($playerElement['startposition'] ?? '');

        // Sanitize and convert score
        $scoreValue = null;
        if ($score !== '') {
            $scoreValue = (float) str_replace(',', '.', preg_replace('/[^0-9,.-]/', '', $score));
        }

        $playerData = [
            'board_game_play_id' => $playId,
            'is_winner' => $win === 1,
            'is_new_player' => $new === 1,
            'score' => $scoreValue,
            'position' => $startPosition !== '' ? (int) $startPosition : null,
        ];

        // Determine identifier type
        if ($username !== '') {
            // Try to find user by BGG username
            $user = \App\Models\User::where('board_game_geek_username', $username)->first();
            if ($user !== null) {
                $playerData['user_id'] = $user->id;
                $playerData['board_game_geek_username'] = null;
                $playerData['guest_name'] = null;
            } else {
                $playerData['user_id'] = null;
                $playerData['board_game_geek_username'] = $username;
                $playerData['guest_name'] = null;
            }
        } else {
            $playerData['user_id'] = null;
            $playerData['board_game_geek_username'] = null;
            $playerData['guest_name'] = $name !== '' ? $name : 'Unknown';
        }

        return $playerData;
    }

    /**
     * Clean up plays that no longer exist on BGG.
     *
     * @param User $user The user
     * @param array<string> $bggPlayIds Array of BGG play IDs that exist on BGG
     * @param string $minDate Minimum date
     * @param string $maxDate Maximum date
     * @return void
     */
    public function cleanupDeletedBggPlays(User $user, array $bggPlayIds, string $minDate, string $maxDate): void
    {
        // Find plays in our database for this user in the date range that aren't in the BGG response
        $playsInDatabase = BoardGamePlay::where('created_by_user_id', $user->id)
            ->where('source', 'boardgamegeek')
            ->whereNotNull('bgg_play_id')
            ->whereBetween('played_at', [$minDate, $maxDate])
            ->whereNotIn('bgg_play_id', $bggPlayIds)
            ->get();

        foreach ($playsInDatabase as $play) {
            $play->delete();
            Log::info('Deleted play that no longer exists on BGG', [
                'play_id' => $play->id,
                'bgg_play_id' => $play->bgg_play_id,
            ]);
        }
    }

    /**
     * Validate that a BGG play meets our criteria.
     *
     * @param SimpleXMLElement $playElement The play XML element
     * @return bool True if valid
     */
    public function validateBggPlay(SimpleXMLElement $playElement): bool
    {
        // Check incomplete flag
        $incomplete = (int) ($playElement['incomplete'] ?? 1);
        if ($incomplete !== 0) {
            return false;
        }

        // Check nowinstats flag
        $nowinstats = (int) ($playElement['nowinstats'] ?? 1);
        if ($nowinstats !== 0) {
            return false;
        }

        // Check quantity
        $quantity = (int) ($playElement['quantity'] ?? 0);
        if ($quantity <= 0) {
            return false;
        }

        // Check if has players
        $players = $playElement->players[0]->player ?? null;
        if ($players === null) {
            return false;
        }
        $playerCount = 0;
        foreach ($players as $player) {
            $playerCount++;
        }
        if ($playerCount === 0) {
            return false;
        }

        // Check subtype
        $subtype = $this->getBggPlaySubtype($playElement);
        $validSubtypes = ['boardgame', 'boardgameexpansion', 'boardgamecompilation'];
        if (!in_array($subtype, $validSubtypes, true)) {
            return false;
        }

        return true;
    }

    /**
     * Get the BGG play subtype.
     *
     * @param SimpleXMLElement $playElement The play XML element
     * @return string|null The subtype or null
     */
    public function getBggPlaySubtype(SimpleXMLElement $playElement): ?string
    {
        if (!isset($playElement->item[0]->subtypes[0]->subtype[0])) {
            return null;
        }

        return (string) $playElement->item[0]->subtypes[0]->subtype[0]['value'];
    }

    /**
     * Trigger board game sync if game doesn't exist locally.
     *
     * @param string $bggGameId The BGG game ID
     * @return void
     */
    public function syncBoardGameIfNeeded(string $bggGameId): void
    {
        $boardGame = BoardGame::where('bgg_id', $bggGameId)->first();
        if ($boardGame === null) {
            // Queue board game sync job
            \App\Jobs\SyncBoardGameFromBoardGameGeekJob::dispatch($bggGameId)
                ->delay(now()->addSeconds(2));
        }
    }
}

