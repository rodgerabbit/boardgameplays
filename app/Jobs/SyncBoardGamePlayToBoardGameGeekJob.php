<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BoardGamePlay;
use App\Services\BoardGameGeekPlaySubmissionService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job for syncing a play to BoardGameGeek.
 *
 * This job submits a play to BoardGameGeek API using one of three
 * authentication methods. The job is idempotent and can be safely retried.
 */
class SyncBoardGamePlayToBoardGameGeekJob implements ShouldQueue
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
     * @param int $playId The play ID to sync
     * @param string|null $providedBggUsername Optional BGG username for provided credentials method
     * @param string|null $providedBggPassword Optional BGG password for provided credentials method
     */
    public function __construct(
        public readonly int $playId,
        public readonly ?string $providedBggUsername = null,
        public readonly ?string $providedBggPassword = null,
    ) {
    }

    /**
     * Execute the job.
     *
     * @param BoardGameGeekPlaySubmissionService $submissionService
     * @return void
     */
    public function handle(BoardGameGeekPlaySubmissionService $submissionService): void
    {
        try {
            $play = BoardGamePlay::with(['boardGame', 'players', 'expansions'])->findOrFail($this->playId);

            if (!$play->sync_to_bgg) {
                Log::info('Play sync to BGG not requested', [
                    'play_id' => $this->playId,
                ]);
                return;
            }

            // Check if board game has BGG ID
            if ($play->boardGame->bgg_id === null) {
                throw new \RuntimeException('Board game does not have a BGG ID');
            }

            Log::info('Starting BGG play submission job', [
                'play_id' => $this->playId,
                'bgg_game_id' => $play->boardGame->bgg_id,
            ]);

            // Get credentials using three methods
            $credentials = $submissionService->getBggCredentialsForPlay(
                $play,
                $this->providedBggUsername,
                $this->providedBggPassword
            );

            // Login to BGG
            $sessionData = $submissionService->loginToBoardGameGeek(
                $credentials['username'],
                $credentials['password']
            );

            // Submit play to BGG
            $response = $submissionService->submitPlayToBoardGameGeek(
                $play,
                $sessionData,
                $play->bgg_play_id
            );

            // Update play with BGG play ID and sync status
            $play->update([
                'bgg_play_id' => (string) $response['playid'],
                'bgg_synced_to_at' => now(),
                'bgg_sync_to_status' => 'synced',
                'bgg_sync_to_error_message' => null,
            ]);

            Log::info('Completed BGG play submission job', [
                'play_id' => $this->playId,
                'bgg_play_id' => $response['playid'],
            ]);
        } catch (\Exception $e) {
            Log::error('BGG play submission job failed', [
                'play_id' => $this->playId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Update play with error status
            $play = BoardGamePlay::find($this->playId);
            if ($play !== null) {
                $submissionService = app(BoardGameGeekPlaySubmissionService::class);
                $submissionService->handleBggSubmissionError($e, $play);
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
        Log::error('BGG play submission job failed permanently', [
            'play_id' => $this->playId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Update play with error status
        $play = BoardGamePlay::find($this->playId);
        if ($play !== null) {
            $submissionService = app(BoardGameGeekPlaySubmissionService::class);
            $submissionService->handleBggSubmissionError($exception, $play);
        }
    }
}

