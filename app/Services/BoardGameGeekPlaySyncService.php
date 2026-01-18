<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\SyncBoardGameFromBoardGameGeekJob;
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
     * Fetch plays from BoardGameGeek API with pagination.
     *
     * @param string $username The BGG username
     * @param string|null $minDate Minimum date (Y-m-d format)
     * @param string|null $maxDate Maximum date (Y-m-d format)
     * @return array<int, SimpleXMLElement> Array of XML play elements
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

                if (!$response->successful()) {
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

                $playCount = 0;
                if ($plays !== null) {
                    $playArray = [];
                    foreach ($plays as $play) {
                        $playArray[] = $play;
                    }
                    $playCount = count($playArray);
                    $allPlays = array_merge($allPlays, $playArray);
                }

                // If we got less than 100 plays, we've reached the last page
                $hasMorePages = $playCount === 100;
                $page++;

                // Rate limiting: wait 2 seconds between requests
                if ($hasMorePages) {
                    sleep(2);
                }
            } catch (\Exception $e) {
                Log::error('Failed to fetch plays from BGG', [
                    'username' => $username,
                    'page' => $page,
                    'error' => $e->getMessage(),
                ]);
                throw new \RuntimeException('Failed to fetch plays from BoardGameGeek: ' . $e->getMessage(), 0, $e);
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
                    continue;
                }

                $play = $this->syncPlayFromBggXml($playElement, $user);
                $processedPlayIds[] = (string) $playElement['id'];
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
     * @param SimpleXMLElement $playElement The play XML element
     * @param User $user The user to sync for
     * @return BoardGamePlay The synced play
     */
    public function syncPlayFromBggXml(SimpleXMLElement $playElement, User $user): BoardGamePlay
    {
        $bggPlayId = (string) $playElement['id'];
        $bggGameId = (string) $playElement->item[0]['objectid'];

        // Check if board game exists, if not trigger sync
        $boardGame = BoardGame::where('bgg_id', $bggGameId)->first();
        if ($boardGame === null) {
            $this->syncBoardGameIfNeeded($bggGameId);
            // Try to find it again after sync job is queued
            $boardGame = BoardGame::where('bgg_id', $bggGameId)->first();
            if ($boardGame === null) {
                throw new \RuntimeException("Board game with BGG ID {$bggGameId} not found and sync failed");
            }
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

        return [
            'board_game_id' => $boardGameId,
            'group_id' => $user->default_group_id,
            'created_by_user_id' => $user->id,
            'played_at' => $playedAt,
            'location' => $location !== '' ? $location : 'Unknown',
            'comment' => $comment !== '' ? $comment : null,
            'game_length_minutes' => $length > 0 ? $length : null,
            'source' => 'boardgamegeek',
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

