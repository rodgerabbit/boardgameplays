<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\User;
use App\Services\BoardGameGeekApiClient;
use App\Services\BoardGameGeekCollectionSyncService;
use Illuminate\Bus\Batch;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Bus;
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
     * Schedule board game sync batches; when all complete, batch then() dispatches the
     * collection import phase immediately (no static delay).
     *
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
        $importQueue = $this->queue ?? 'default';

        $chunks = array_chunk($missingBggIds, $batchSize);
        $batchJobs = [];
        $delaySeconds = 2;
        foreach ($chunks as $chunk) {
            $batchJobs[] = (new SyncBoardGamesBatchFromBoardGameGeekJob($chunk))
                ->onQueue($boardGameQueue)
                ->delay(now()->addSeconds($delaySeconds));
            $delaySeconds += 2;
        }

        Bus::batch($batchJobs)
            ->name('bgg-board-games-then-collection-import')
            ->withOption('import_type', 'collection')
            ->withOption('user_id', $this->userId)
            ->withOption('import_queue', $importQueue)
            ->then(function (Batch $batch): void {
                $opts = $batch->options ?? [];
                if (($opts['import_type'] ?? '') !== 'collection') {
                    return;
                }
                $userId = (int) ($opts['user_id'] ?? 0);
                if ($userId === 0) {
                    Log::warning('BGG collection import batch then: missing user_id in options');
                    return;
                }
                SyncUserCollectionFromBoardGameGeekJob::dispatch($userId, true)
                    ->onQueue($opts['import_queue'] ?? 'default');
            })
            ->onQueue($boardGameQueue)
            ->dispatch();

        Log::info('BGG collection chunk job: scheduled board game batch and import phase via then()', [
            'user_id' => $this->userId,
            'missing_bgg_ids_count' => count($missingBggIds),
            'batches' => count($batchJobs),
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
