<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\BoardGameGeekApiClient;
use App\Services\BoardGameGeekCollectionSyncService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job that resolves one chunk of BGG thing IDs to base game IDs and merges into cache.
 * The last chunk (chunk_index === total_chunks - 1) also finalizes the collection sync.
 */
class SyncCollectionThingIdsChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * Seconds the job may run (one rate-limited request per chunk).
     *
     * @var int
     */
    public int $timeout;

    /**
     * @param int $userId User whose collection is syncing
     * @param int $chunkIndex 0-based index of this chunk
     * @param int $totalChunks Total number of chunks
     * @param array<string> $thingIds BGG thing/object IDs for this chunk
     */
    public function __construct(
        public readonly int $userId,
        public readonly int $chunkIndex,
        public readonly int $totalChunks,
        public readonly array $thingIds,
    ) {
        $this->timeout = (int) config('boardgamegeek.collection_chunk_job_timeout_seconds', 120);
    }

    public function handle(
        BoardGameGeekApiClient $apiClient,
        BoardGameGeekCollectionSyncService $collectionSyncService
    ): void {
        $user = User::find($this->userId);
        if ($user === null) {
            Log::warning('SyncCollectionThingIdsChunkJob: user not found', ['user_id' => $this->userId]);
            return;
        }

        if ($this->thingIds === []) {
            if ($this->chunkIndex === $this->totalChunks - 1) {
                $result = $collectionSyncService->finalizeCollectionSyncAfterChunks($user);
                $this->scheduleBatchesAndImportIfNeeded($result);
            }
            return;
        }

        $baseGameIds = $apiClient->fetchBaseGameIdsForThingIds($this->thingIds);
        $collectionSyncService->mergeResolvedBaseGameIds($user, $baseGameIds);

        if ($this->chunkIndex !== $this->totalChunks - 1) {
            return;
        }

        $result = $collectionSyncService->finalizeCollectionSyncAfterChunks($user);
        $this->scheduleBatchesAndImportIfNeeded($result);
    }

    /**
     * @param array{missing_bgg_ids: array<string>, sync_id: int}|null $result
     */
    private function scheduleBatchesAndImportIfNeeded(?array $result): void
    {
        if ($result === null || ! isset($result['missing_bgg_ids'], $result['sync_id'])) {
            return;
        }

        $missingBggIds = $result['missing_bgg_ids'];
        $batchSize = (int) config('boardgamegeek.rate_limiting.max_ids_per_request', 20);
        $boardGameQueue = config('boardgamegeek.board_game_sync_queue', 'default');
        $delayMinutes = (int) config('boardgamegeek.import_phase_delay_minutes', 10);

        $importQueue = $this->queue ?? 'default';
        SyncUserCollectionFromBoardGameGeekJob::dispatch($this->userId, true)
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

        Log::info('BGG collection chunk job: scheduled board game batches and import phase', [
            'user_id' => $this->userId,
            'missing_bgg_ids_count' => count($missingBggIds),
            'batches' => count($batches),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncCollectionThingIdsChunkJob failed', [
            'user_id' => $this->userId,
            'chunk_index' => $this->chunkIndex,
            'error' => $exception->getMessage(),
        ]);
    }
}
