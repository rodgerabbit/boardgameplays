<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncBoardGamePlaysFromBoardGameGeekJob;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Command to sync board game plays from BoardGameGeek for all users.
 *
 * This command finds all users with BGG usernames and queues sync jobs
 * for each user to sync their plays from the last 30 days.
 */
class SyncBoardGamePlaysFromBoardGameGeekCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bgg:sync-plays
                            {--user-id= : Sync plays for a specific user ID only}
                            {--min-date= : Minimum date (Y-m-d format). Defaults to 30 days ago}
                            {--max-date= : Maximum date (Y-m-d format). Defaults to today}
                            {--delay=2 : Delay in seconds between queuing jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync board game plays from BoardGameGeek for all users with BGG usernames';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $userId = $this->option('user-id');
        $minDate = $this->option('min-date');
        $maxDate = $this->option('max-date');
        $delay = (int) $this->option('delay');

        // Calculate default date range if not provided
        if ($maxDate === null) {
            $maxDate = now()->format('Y-m-d');
        }
        if ($minDate === null) {
            $minDate = now()->subDays(30)->format('Y-m-d');
        }

        // Get users to sync
        if ($userId !== null) {
            $users = User::where('id', $userId)
                ->whereNotNull('board_game_geek_username')
                ->get();
        } else {
            $users = User::whereNotNull('board_game_geek_username')->get();
        }

        if ($users->isEmpty()) {
            $this->warn('No users with BGG usernames found.');
            return self::FAILURE;
        }

        $this->info("Found {$users->count()} user(s) with BGG usernames to sync.");
        $this->info("Date range: {$minDate} to {$maxDate}");

        $queuedCount = 0;
        $currentDelay = 0;

        foreach ($users as $user) {
            $this->info("Queueing sync job for user {$user->id} ({$user->board_game_geek_username})...");

            SyncBoardGamePlaysFromBoardGameGeekJob::dispatch($user->id, $minDate, $maxDate)
                ->delay(now()->addSeconds($currentDelay));

            $queuedCount++;
            $currentDelay += $delay;

            Log::info('Queued BGG plays sync job', [
                'user_id' => $user->id,
                'bgg_username' => $user->board_game_geek_username,
                'min_date' => $minDate,
                'max_date' => $maxDate,
                'delay_seconds' => $currentDelay,
            ]);
        }

        $this->info("Successfully queued {$queuedCount} sync job(s).");

        return self::SUCCESS;
    }
}

