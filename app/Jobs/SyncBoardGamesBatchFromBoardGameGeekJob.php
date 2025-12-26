<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\BoardGameGeekSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job for syncing multiple board games from BoardGameGeek in a single batch.
 *
 * This job fetches board game data from BoardGameGeek API for up to 20 IDs
 * in a single API call and updates or creates the corresponding BoardGame records.
 * The job is idempotent and can be safely retried.
 */
class SyncBoardGamesBatchFromBoardGameGeekJob implements ShouldQueue
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
     * @param array<string> $bggIds Array of BoardGameGeek IDs to sync (max 20)
     */
    public function __construct(
        public readonly array $bggIds,
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
            Log::info('Starting BoardGameGeek batch sync job', [
                'bgg_ids' => $this->bggIds,
                'count' => count($this->bggIds),
            ]);

            $syncService->syncBoardGamesByBggIds($this->bggIds);

            Log::info('Completed BoardGameGeek batch sync job', [
                'bgg_ids' => $this->bggIds,
                'count' => count($this->bggIds),
            ]);
        } catch (\Exception $e) {
            Log::error('BoardGameGeek batch sync job failed', [
                'bgg_ids' => $this->bggIds,
                'count' => count($this->bggIds),
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

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
        Log::error('BoardGameGeek batch sync job failed permanently', [
            'bgg_ids' => $this->bggIds,
            'count' => count($this->bggIds),
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);
    }
}
