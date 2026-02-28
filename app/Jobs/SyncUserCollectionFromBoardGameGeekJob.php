<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\BoardGameGeekCollectionSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job to sync a single user's BGG collection (async).
 *
 * Phase 1: Fetches collection, scans for missing board games. Schedules batch sync jobs
 * (with priority) for missing games, then re-dispatches this job as import phase.
 * Phase 2 (import): Imports collection from cache after board games exist.
 */
class SyncUserCollectionFromBoardGameGeekJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $userId,
        public readonly bool $isImportPhase = false,
    ) {
    }

    public function handle(BoardGameGeekCollectionSyncService $collectionSyncService): void
    {
        $user = User::find($this->userId);
        if ($user === null) {
            Log::warning('SyncUserCollectionFromBoardGameGeekJob: user not found', ['user_id' => $this->userId]);
            return;
        }

        if ($user->board_game_geek_username === null && ! $this->isImportPhase) {
            Log::info('SyncUserCollectionFromBoardGameGeekJob: user has no BGG username, skipping', ['user_id' => $this->userId]);
            return;
        }

        $result = $collectionSyncService->syncCollectionForUser($user, $this->isImportPhase);

        if ($result !== null && isset($result['missing_bgg_ids'], $result['sync_id'])) {
            $missingBggIds = $result['missing_bgg_ids'];
            $batchSize = (int) config('boardgamegeek.rate_limiting.max_ids_per_request', 20);
            $boardGameQueue = config('boardgamegeek.board_game_sync_queue', 'default');
            $delayMinutes = (int) config('boardgamegeek.import_phase_delay_minutes', 10);

            $importQueue = $this->queue ?? 'default';
            self::dispatch($this->userId, true)
                ->onQueue($importQueue)
                ->delay(now()->addMinutes($delayMinutes));

            $batches = array_chunk($missingBggIds, $batchSize);
            $delaySeconds = 2;
            foreach ($batches as $batch) {
                SyncBoardGamesBatchFromBoardGameGeekJob::dispatch($batch)
                    ->onQueue($boardGameQueue)
                    ->delay(now()->addSeconds($delaySeconds));
                $delaySeconds += 2;
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncUserCollectionFromBoardGameGeekJob failed permanently', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}
