<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\SyncBoardGamesBatchFromBoardGameGeekJob;
use App\Models\BoardGame;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Command to refresh all board games from BoardGameGeek using batch jobs.
 *
 * This command fetches BoardGame records that have a BoardGameGeek ID and either:
 * - Have never been synced (bgg_synced_at is null), or
 * - Have not been synced in the last 3 months
 *
 * It then queues batch sync jobs to refresh their data from the BGG API.
 * The jobs are queued with appropriate delays to respect BGG rate limits.
 */
class RefreshAllBoardGamesFromBoardGameGeekCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'boardgamegeek:refresh-all
                            {--limit= : Limit the number of board games to refresh}
                            {--delay=2 : Delay in seconds between queuing batch jobs}
                            {--months=3 : Number of months to consider as stale (default: 3)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refresh stale board games from BoardGameGeek using batch jobs (never synced or not synced in last 3 months)';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle(): int
    {
        $limit = $this->option('limit') ? (int) $this->option('limit') : null;
        $delay = (int) $this->option('delay');
        $months = (int) $this->option('months');

        $this->info("Fetching board games with BoardGameGeek IDs that need refreshing...");
        $this->info("Including games that have never been synced or haven't been synced in the last {$months} months.");

        // Get board games that have a BGG ID and either:
        // 1. Have never been synced (bgg_synced_at is null), or
        // 2. Have not been synced in the last N months
        $staleDate = now()->subMonths($months);

        $query = BoardGame::whereNotNull('bgg_id')
            ->where('bgg_id', '!=', '')
            ->where(function ($query) use ($staleDate) {
                $query->whereNull('bgg_synced_at')
                    ->orWhere('bgg_synced_at', '<', $staleDate);
            });

        $totalCount = $query->count();

        if ($totalCount === 0) {
            $this->info('No board games found that need refreshing.');
            $this->info("All board games with BoardGameGeek IDs have been synced within the last {$months} months.");
            return self::SUCCESS;
        }

        $this->info("Found {$totalCount} board games that need refreshing.");

        // Apply limit if specified
        if ($limit !== null) {
            $query->limit($limit);
            $this->info("Limited to {$limit} board games.");
        }

        // Get all BGG IDs
        $bggIds = $query->pluck('bgg_id')
            ->map(fn ($id) => (string) $id)
            ->filter(fn ($id) => !empty($id) && is_numeric($id))
            ->unique()
            ->values()
            ->toArray();

        if (empty($bggIds)) {
            $this->warn('No valid BoardGameGeek IDs found.');
            return self::FAILURE;
        }

        $this->info("Processing " . count($bggIds) . " unique BoardGameGeek IDs.");

        $maxIdsPerRequest = (int) config('boardgamegeek.rate_limiting.max_ids_per_request', 20);

        $this->info("Queueing batch sync jobs (up to {$maxIdsPerRequest} IDs per job) with {$delay} second delay between batches...");

        // Split into batches of max IDs per request
        $batches = array_chunk($bggIds, $maxIdsPerRequest);
        $queued = 0;
        $batchIndex = 0;

        foreach ($batches as $batch) {
            // Queue the batch job with increasing delay to respect rate limits
            SyncBoardGamesBatchFromBoardGameGeekJob::dispatch($batch)
                ->delay(now()->addSeconds($delay * $batchIndex));

            $queued += count($batch);
            $batchIndex++;

            if ($batchIndex % 10 === 0 || $batchIndex === count($batches)) {
                $this->info("Queued {$batchIndex} batch jobs ({$queued} total IDs)...");
            }
        }

        $this->info("Successfully queued {$batchIndex} batch jobs ({$queued} total IDs).");
        $this->info("Jobs will be processed with {$delay} second delays between batches to respect rate limits.");

        Log::info('BoardGameGeek refresh all command completed', [
            'total_board_games_needing_refresh' => $totalCount,
            'unique_bgg_ids' => count($bggIds),
            'batch_jobs_queued' => $batchIndex,
            'total_ids_queued' => $queued,
            'delay_between_batches' => $delay,
            'stale_months_threshold' => $months,
            'stale_date_cutoff' => $staleDate->toDateTimeString(),
        ]);

        return self::SUCCESS;
    }
}
