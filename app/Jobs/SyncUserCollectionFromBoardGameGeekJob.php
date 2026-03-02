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
 * Phase 1: Fetches collection only, caches items, and dispatches SyncCollectionThingIdsChunkJob
 * per chunk so thing-ID resolution runs in separate jobs (avoids timeout). The last chunk job
 * finalizes and may schedule board game batch syncs + import phase.
 * Phase 2 (import): Imports collection from cache after board games exist.
 */
class SyncUserCollectionFromBoardGameGeekJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    /**
     * Seconds the job may run. Phase 1 only fetches collection (one request); chunk jobs do the rest.
     *
     * @var int
     */
    public int $timeout;

    public function __construct(
        public readonly int $userId,
        public readonly bool $isImportPhase = false,
    ) {
        $this->timeout = (int) config('boardgamegeek.collection_sync_job_timeout_seconds', 180);
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

        if ($result === null) {
            return;
        }

        $chunkThingIds = $result['chunk_thing_ids'] ?? [];
        if ($chunkThingIds === []) {
            return;
        }

        $queueName = $this->queue ?? 'default';
        $delaySeconds = (int) config('boardgamegeek.rate_limiting.minimum_seconds_between_requests', 2);

        foreach ($chunkThingIds as $index => $thingIds) {
            SyncCollectionThingIdsChunkJob::dispatch($this->userId, $index, count($chunkThingIds), $thingIds)
                ->onQueue($queueName)
                ->delay(now()->addSeconds($delaySeconds * $index));
        }

        Log::info('SyncUserCollectionFromBoardGameGeekJob: dispatched thing-ID chunk jobs', [
            'user_id' => $this->userId,
            'chunks' => count($chunkThingIds),
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncUserCollectionFromBoardGameGeekJob failed permanently', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}
