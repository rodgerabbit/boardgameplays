<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BoardGamePlay;
use App\Models\User;
use App\Services\BoardGameGeekPlaySyncService;
use App\Services\BoardGamePlayDeduplicationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Job for syncing plays from BoardGameGeek for a user.
 *
 * This job fetches plays from BoardGameGeek API for a specific user
 * and syncs them to the local database. The job is idempotent and can be safely retried.
 */
class SyncBoardGamePlaysFromBoardGameGeekJob implements ShouldQueue
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
     * @param int $userId The user ID to sync plays for
     * @param string|null $minDate Minimum date (Y-m-d format), defaults to 30 days ago
     * @param string|null $maxDate Maximum date (Y-m-d format), defaults to today
     */
    public function __construct(
        public readonly int $userId,
        public readonly ?string $minDate = null,
        public readonly ?string $maxDate = null,
    ) {
    }

    /**
     * Execute the job.
     *
     * @param BoardGameGeekPlaySyncService $syncService
     * @param BoardGamePlayDeduplicationService $deduplicationService
     * @return void
     */
    public function handle(
        BoardGameGeekPlaySyncService $syncService,
        BoardGamePlayDeduplicationService $deduplicationService
    ): void {
        try {
            $user = User::findOrFail($this->userId);

            if ($user->board_game_geek_username === null) {
                Log::warning('Cannot sync plays for user without BGG username', [
                    'user_id' => $this->userId,
                ]);
                return;
            }

            // Calculate date range (default to last 30 days)
            $maxDate = $this->maxDate ?? now()->format('Y-m-d');
            $minDate = $this->minDate ?? now()->subDays(30)->format('Y-m-d');

            Log::info('Starting BGG plays sync job', [
                'user_id' => $this->userId,
                'bgg_username' => $user->board_game_geek_username,
                'min_date' => $minDate,
                'max_date' => $maxDate,
            ]);

            // Fetch plays from BGG
            $plays = $syncService->fetchPlaysFromBoardGameGeek(
                $user->board_game_geek_username,
                $minDate,
                $maxDate
            );

            // Process and sync plays
            $processedPlayIds = $syncService->processBggPlaysXml($plays, $user);

            // Cleanup deleted plays
            $syncService->cleanupDeletedBggPlays($user, $processedPlayIds, $minDate, $maxDate);

            // Update sync status for all synced plays
            BoardGamePlay::where('created_by_user_id', $user->id)
                ->where('source', 'boardgamegeek')
                ->whereIn('bgg_play_id', $processedPlayIds)
                ->update([
                    'bgg_synced_at' => now(),
                    'bgg_sync_status' => 'synced',
                    'bgg_sync_error_message' => null,
                ]);

            Log::info('Completed BGG plays sync job', [
                'user_id' => $this->userId,
                'plays_synced' => count($processedPlayIds),
            ]);

            $this->runDeduplicationForSyncedPlays($deduplicationService, $minDate, $maxDate, $processedPlayIds);
        } catch (\Exception $e) {
            Log::error('BGG plays sync job failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);

            // Update sync status for user's plays
            BoardGamePlay::where('created_by_user_id', $this->userId)
                ->where('source', 'boardgamegeek')
                ->whereNull('bgg_sync_status')
                ->orWhere('bgg_sync_status', 'pending')
                ->update([
                    'bgg_sync_status' => 'failed',
                    'bgg_sync_error_message' => substr($e->getMessage(), 0, 500),
                ]);

            throw $e; // Re-throw to trigger retry mechanism
        }
    }

    /**
     * Run play deduplication for each group that had plays synced in this job, for the processed date range.
     *
     * @param BoardGamePlayDeduplicationService $deduplicationService
     * @param string $minDate Minimum date (Y-m-d) that was synced
     * @param string $maxDate Maximum date (Y-m-d) that was synced
     * @param array<string> $processedPlayIds BGG play IDs that were processed
     * @return void
     */
    private function runDeduplicationForSyncedPlays(
        BoardGamePlayDeduplicationService $deduplicationService,
        string $minDate,
        string $maxDate,
        array $processedPlayIds
    ): void {
        if ($processedPlayIds === []) {
            return;
        }

        $groupIds = BoardGamePlay::query()
            ->where('created_by_user_id', $this->userId)
            ->where('source', 'boardgamegeek')
            ->whereIn('bgg_play_id', $processedPlayIds)
            ->whereNotNull('group_id')
            ->distinct()
            ->pluck('group_id')
            ->values()
            ->all();

        if ($groupIds === []) {
            return;
        }

        $from = Carbon::parse($minDate)->startOfDay();
        $to = Carbon::parse($maxDate)->startOfDay();

        foreach ($groupIds as $groupId) {
            $deduplicationService->syncDeduplicationForGroupAndDateRange((int) $groupId, $from, $to);
        }

        Log::info('Ran play deduplication after BGG sync', [
            'user_id' => $this->userId,
            'group_ids' => $groupIds,
            'from' => $minDate,
            'to' => $maxDate,
        ]);
    }

    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BGG plays sync job failed permanently', [
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Update sync status for user's plays
        BoardGamePlay::where('created_by_user_id', $this->userId)
            ->where('source', 'boardgamegeek')
            ->where(function ($q) {
                $q->whereNull('bgg_sync_status')
                    ->orWhere('bgg_sync_status', 'pending');
            })
            ->update([
                'bgg_sync_status' => 'failed',
                'bgg_sync_error_message' => substr($exception->getMessage(), 0, 500),
            ]);
    }
}

