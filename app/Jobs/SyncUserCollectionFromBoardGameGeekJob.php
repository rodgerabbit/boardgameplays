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
 * Fetches collection from BGG, tracks changes, upserts items, and queues
 * board game sync for any missing games.
 */
class SyncUserCollectionFromBoardGameGeekJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly int $userId,
    ) {
    }

    public function handle(BoardGameGeekCollectionSyncService $collectionSyncService): void
    {
        $user = User::find($this->userId);
        if ($user === null) {
            Log::warning('SyncUserCollectionFromBoardGameGeekJob: user not found', ['user_id' => $this->userId]);
            return;
        }

        if ($user->board_game_geek_username === null) {
            Log::info('SyncUserCollectionFromBoardGameGeekJob: user has no BGG username, skipping', ['user_id' => $this->userId]);
            return;
        }

        $collectionSyncService->syncCollectionForUser($user);
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('SyncUserCollectionFromBoardGameGeekJob failed permanently', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
        ]);
    }
}
