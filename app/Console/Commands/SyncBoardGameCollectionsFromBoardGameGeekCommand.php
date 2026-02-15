<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncUserCollectionFromBoardGameGeekJob;
use App\Models\BggCollectionSync;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Command to sync BGG collections for users (async jobs).
 *
 * Queues a collection sync job for each user with a BGG username whose
 * last successful sync is older than the configured interval (default 7 days),
 * or who has never been synced.
 */
class SyncBoardGameCollectionsFromBoardGameGeekCommand extends Command
{
    protected $signature = 'bgg:sync-collections
                            {--user-id= : Sync collection for a specific user ID only}
                            {--force : Queue sync for all users with BGG username regardless of last sync}
                            {--delay=2 : Delay in seconds between queuing each user job}';

    protected $description = 'Queue BGG collection sync jobs for users (syncs every 7 days by default)';

    public function handle(): int
    {
        $userId = $this->option('user-id');
        $force = (bool) $this->option('force');
        $delaySeconds = (int) $this->option('delay');
        $intervalDays = config('boardgamegeek.collection_sync_interval_days', 7);
        $cutoff = now()->subDays($intervalDays);

        $query = User::whereNotNull('board_game_geek_username')
            ->where('board_game_geek_username', '!=', '');

        if ($userId !== null) {
            $query->where('id', $userId);
        }

        $users = $query->get();

        if ($users->isEmpty()) {
            $this->warn('No users with BGG usernames found.');
            return self::FAILURE;
        }

        if (!$force) {
            $userIdsWithRecentSync = BggCollectionSync::where('status', BggCollectionSync::STATUS_SUCCESS)
                ->where('synced_at', '>=', $cutoff)
                ->pluck('user_id')
                ->unique()
                ->all();
            $users = $users->whereNotIn('id', $userIdsWithRecentSync)->values();
        }

        if ($users->isEmpty()) {
            $this->info('No users due for collection sync (all synced within the last ' . $intervalDays . ' days).');
            return self::SUCCESS;
        }

        $this->info('Queueing ' . $users->count() . ' BGG collection sync job(s).');

        $currentDelay = 0;
        foreach ($users as $user) {
            SyncUserCollectionFromBoardGameGeekJob::dispatch($user->id)
                ->delay(now()->addSeconds($currentDelay));

            $this->line("  Queued user {$user->id} ({$user->board_game_geek_username}) in {$currentDelay}s");
            $currentDelay += $delaySeconds;

            Log::info('Queued BGG collection sync job', [
                'user_id' => $user->id,
                'bgg_username' => $user->board_game_geek_username,
                'delay_seconds' => $currentDelay,
            ]);
        }

        $this->info('Done.');
        return self::SUCCESS;
    }
}
