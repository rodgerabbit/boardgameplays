<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BoardGame;
use App\Services\BoardGameGeekSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job for syncing a board game from BoardGameGeek.
 *
 * This job fetches board game data from BoardGameGeek API and updates
 * or creates the corresponding BoardGame record in the local database.
 * The job is idempotent and can be safely retried.
 */
class SyncBoardGameFromBoardGameGeekJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 5;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public int $backoff = 3;

    /**
     * Create a new job instance.
     *
     * @param string $bggId The BoardGameGeek ID to sync
     * @param int|null $boardGameId Optional BoardGame model ID if it already exists
     */
    public function __construct(
        public readonly string $bggId,
        public readonly ?int $boardGameId = null,
    ) {
    }

    /**
     * Execute the job.
     *
     * @param BoardGameGeekSyncService $syncService
     * @return void
     */
    public function handle(BoardGameGeekSyncService $syncService): void
    {
        try {
            Log::info('Starting BoardGameGeek sync job', [
                'bgg_id' => $this->bggId,
                'board_game_id' => $this->boardGameId,
            ]);

            $syncService->syncBoardGameByBggId($this->bggId);

            Log::info('Completed BoardGameGeek sync job', [
                'bgg_id' => $this->bggId,
            ]);
        } catch (\Exception $e) {
            Log::error('BoardGameGeek sync job failed', [
                'bgg_id' => $this->bggId,
                'board_game_id' => $this->boardGameId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Mark the board game as failed if it exists
            if ($this->boardGameId !== null) {
                BoardGame::where('id', $this->boardGameId)->update([
                    'bgg_sync_status' => 'failed',
                    'bgg_sync_error_message' => substr($e->getMessage(), 0, 500),
                ]);
            }

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BoardGameGeek sync job failed permanently', [
            'bgg_id' => $this->bggId,
            'board_game_id' => $this->boardGameId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Mark the board game as failed if it exists
        if ($this->boardGameId !== null) {
            BoardGame::where('id', $this->boardGameId)->update([
                'bgg_sync_status' => 'failed',
                'bgg_sync_error_message' => substr($exception->getMessage(), 0, 500),
            ]);
        }
    }
}
