<?php

declare(strict_types=1);

namespace App\Services;

use App\DTOs\BoardGameGeekGameDto;
use App\Models\BoardGame;
use Illuminate\Support\Facades\Log;

/**
 * Service class for syncing board games from BoardGameGeek.
 *
 * This service handles the synchronization of board game data from BoardGameGeek
 * to the local database. It uses the BoardGameGeekApiClient to fetch data
 * and updates or creates BoardGame records.
 */
class BoardGameGeekSyncService extends BaseService
{
    /**
     * Create a new BoardGameGeekSyncService instance.
     */
    public function __construct(
        private readonly BoardGameGeekApiClient $apiClient,
    ) {
    }

    /**
     * Sync a single board game by its BGG ID.
     *
     * @param string $bggId The BoardGameGeek ID
     * @return BoardGame The synced or created board game
     * @throws \RuntimeException If the sync fails
     */
    public function syncBoardGameByBggId(string $bggId): BoardGame
    {
        $gameDtos = $this->apiClient->fetchBoardGamesByIds([$bggId]);

        if (empty($gameDtos)) {
            throw new \RuntimeException("No board game found with BGG ID: {$bggId}");
        }

        $gameDto = $gameDtos[0];
        return $this->updateOrCreateBoardGame($gameDto);
    }

    /**
     * Sync multiple board games by their BGG IDs.
     *
     * @param array<int|string> $bggIds Array of BoardGameGeek IDs
     * @return array<BoardGame> Array of synced board games
     */
    public function syncBoardGamesByBggIds(array $bggIds): array
    {
        $syncedGames = [];

        // Split into chunks of max IDs per request
        $chunks = array_chunk($bggIds, config('boardgamegeek.rate_limiting.max_ids_per_request'));

        foreach ($chunks as $chunk) {
            try {
                $gameDtos = $this->apiClient->fetchBoardGamesByIds($chunk);

                foreach ($gameDtos as $gameDto) {
                    $syncedGames[] = $this->updateOrCreateBoardGame($gameDto);
                }
            } catch (\Exception $e) {
                Log::error('Failed to sync chunk of board games from BoardGameGeek', [
                    'bgg_ids' => $chunk,
                    'error' => $e->getMessage(),
                ]);

                // Mark games in this chunk as failed
                foreach ($chunk as $bggId) {
                    $this->markSyncFailed($bggId, $e->getMessage());
                }
            }
        }

        return $syncedGames;
    }

    /**
     * Update or create a board game from a DTO.
     *
     * @param BoardGameGeekGameDto $gameDto The game DTO
     * @return BoardGame The updated or created board game
     */
    private function updateOrCreateBoardGame(BoardGameGeekGameDto $gameDto): BoardGame
    {
        $data = array_merge(
            $gameDto->toArray(),
            [
                'bgg_synced_at' => now(),
                'bgg_sync_status' => 'success',
                'bgg_sync_error_message' => null,
            ]
        );

        // Ensure required fields have defaults if null
        if ($data['min_players'] === null) {
            $data['min_players'] = 1; // Default minimum players
        }
        if ($data['max_players'] === null) {
            $data['max_players'] = 99; // Default maximum players (high number for unknown)
        }

        $boardGame = BoardGame::updateOrCreate(
            ['bgg_id' => $gameDto->bggId],
            $data
        );

        Log::info('Synced board game from BoardGameGeek', [
            'bgg_id' => $gameDto->bggId,
            'name' => $gameDto->name,
            'board_game_id' => $boardGame->id,
        ]);

        return $boardGame;
    }

    /**
     * Mark a board game sync as failed.
     *
     * @param string $bggId The BoardGameGeek ID
     * @param string $errorMessage The error message
     * @return void
     */
    private function markSyncFailed(string $bggId, string $errorMessage): void
    {
        BoardGame::where('bgg_id', $bggId)->update([
            'bgg_sync_status' => 'failed',
            'bgg_sync_error_message' => substr($errorMessage, 0, 500), // Limit error message length
        ]);
    }
}

