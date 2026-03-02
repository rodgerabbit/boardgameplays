<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\BoardGame;
use App\Models\BoardGamePlay;
use App\Models\BggPlaysSync;
use App\Models\User;
use App\Services\BoardGameGeekPlaySyncService;
use App\Services\BoardGamePlayDeduplicationService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use SimpleXMLElement;

/**
 * Job for syncing plays from BoardGameGeek for a user.
 *
 * Two-phase flow: (1) Fetch plays, scan for missing board games; schedule batch syncs
 * (priority queue) and cache play payloads; re-dispatch as import phase. (2) Import phase:
 * load cache, create plays for payloads whose board game exists, then cleanup and dedupe.
 */
class SyncBoardGamePlaysFromBoardGameGeekJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const CACHE_KEY_PREFIX = 'bgg_plays_pending_';

    public int $tries = 5;

    public int $backoff = 3;

    /**
     * Seconds the job may run before the worker kills it.
     * Each run fetches one BGG page; config allows for slow API + processing.
     *
     * @var int
     */
    public int $timeout;

    public function __construct(

    /**
     * @param int $userId The user ID to sync plays for
     * @param string|null $minDate Minimum date (Y-m-d format), defaults to 30 days ago
     * @param string|null $maxDate Maximum date (Y-m-d format), defaults to today
     * @param bool $requestedManually Whether the user triggered this sync from Settings
     * @param bool $isImportPhase True when running the deferred import after board games are synced
     * @param int $page Page number to fetch from BGG (1-based) when not in import phase
     */
    public function __construct(
        public readonly int $userId,
        public readonly ?string $minDate = null,
        public readonly ?string $maxDate = null,
        public readonly bool $requestedManually = false,
        public readonly bool $isImportPhase = false,
        public readonly int $page = 1,
    ) {
        $this->timeout = (int) config('boardgamegeek.plays_sync_job_timeout_seconds', 120);
    }

    public function handle(
        BoardGameGeekPlaySyncService $syncService,
        BoardGamePlayDeduplicationService $deduplicationService
    ): void {
        $user = User::find($this->userId);
        if ($user === null) {
            Log::warning('SyncBoardGamePlaysFromBoardGameGeekJob: user not found', ['user_id' => $this->userId]);
            return;
        }

        if ($this->isImportPhase) {
            $this->runImportPhase($user, $syncService, $deduplicationService);
            return;
        }

        if ($user->board_game_geek_username === null) {
            Log::warning('Cannot sync plays for user without BGG username', ['user_id' => $this->userId]);
            return;
        }

        $maxDate = $this->maxDate ?? now()->format('Y-m-d');
        $minDate = $this->minDate ?? now()->subDays(30)->format('Y-m-d');

        $cacheKey = self::CACHE_KEY_PREFIX . $this->userId;

        // Create or reuse the BggPlaysSync tracking record. We only create it on the
        // first page and reuse it for subsequent pages via the cached sync_id.
        $existingCached = Cache::get($cacheKey);
        if ($this->page === 1 || ! is_array($existingCached) || ! isset($existingCached['sync_id'])) {
            $playsSync = BggPlaysSync::create([
                'user_id' => $this->userId,
                'synced_at' => now(),
                'status' => BggPlaysSync::STATUS_PENDING,
                'requested_manually' => $this->requestedManually,
            ]);
            $syncId = $playsSync->id;
        } else {
            $syncId = (int) $existingCached['sync_id'];
            $playsSync = BggPlaysSync::find($syncId);
        }

        try {
            Log::info('Starting BGG plays sync job (phase 1)', [
                'user_id' => $this->userId,
                'bgg_username' => $user->board_game_geek_username,
                'min_date' => $minDate,
                'max_date' => $maxDate,
                'page' => $this->page,
            ]);

            $pageResult = $syncService->fetchPlaysPageFromBoardGameGeek(
                $user->board_game_geek_username,
                $minDate,
                $maxDate,
                $this->page
            );

            $plays = $pageResult['plays'];
            $hasMorePages = $pageResult['has_more_pages'];

            $payloadsForPage = $this->buildPayloadsFromPlays($plays, $user, $syncService);

            // Merge this page's payloads into the cached collection so that the
            // final page has all plays available for missing-game detection and import.
            $ttlMinutes = (int) config('boardgamegeek.pending_import_cache_ttl_minutes', 60);
            $cached = Cache::get($cacheKey);
            $existingPayloads = [];
            if (is_array($cached) && isset($cached['payloads']) && is_array($cached['payloads'])) {
                $existingPayloads = $cached['payloads'];
            }

            $allPayloads = array_merge($existingPayloads, $payloadsForPage);

            Cache::put($cacheKey, [
                'payloads' => $allPayloads,
                'min_date' => $minDate,
                'max_date' => $maxDate,
                'sync_id' => $syncId,
            ], now()->addMinutes($ttlMinutes));

            if ($hasMorePages) {
                // Queue the next page with a small delay to respect BGG rate limits.
                $nextPage = $this->page + 1;
                $queueName = $this->queue ?? 'default';

                self::dispatch(
                    $this->userId,
                    $this->minDate,
                    $this->maxDate,
                    $this->requestedManually,
                    false,
                    $nextPage
                )
                    ->onQueue($queueName)
                    ->delay(now()->addSeconds(2));

                Log::info('BGG plays sync: queued next page', [
                    'user_id' => $this->userId,
                    'current_page' => $this->page,
                    'next_page' => $nextPage,
                ]);

                return;
            }

            // We are on the last page: compute missing games based on all payloads and either
            // perform immediate import (when everything exists) or schedule board game sync
            // batches plus a deferred import phase.
            $missingBggIds = $syncService->getMissingBggGameIdsFromPayloads($allPayloads);

            if ($missingBggIds === []) {
                $processedPlayIds = [];
                foreach ($allPayloads as $payload) {
                    $bggGameId = $payload['bgg_game_id'] ?? null;
                    if ($bggGameId === null || $bggGameId === '') {
                        continue;
                    }
                    $boardGame = BoardGame::where('bgg_id', (string) $bggGameId)->first();
                    if ($boardGame === null || $boardGame->is_expansion) {
                        continue;
                    }
                    $play = $syncService->createPlayFromPayload($payload, $user, $boardGame->id);
                    $processedPlayIds[] = $play->bgg_play_id;
                }

                $allBggPlayIdsFromBgg = array_values(array_filter(array_column($allPayloads, 'bgg_play_id')));
                $syncService->cleanupDeletedBggPlays($user, $allBggPlayIdsFromBgg, $minDate, $maxDate);
                $this->markPlaysSynced($user, $processedPlayIds);

                if ($playsSync !== null) {
                    $playsSync->update([
                        'status' => BggPlaysSync::STATUS_SUCCESS,
                        'plays_count' => count($processedPlayIds),
                        'error_message' => null,
                    ]);
                } else {
                    BggPlaysSync::where('id', $syncId)->update([
                        'status' => BggPlaysSync::STATUS_SUCCESS,
                        'plays_count' => count($processedPlayIds),
                        'error_message' => null,
                    ]);
                }

                Cache::forget($cacheKey);

                $this->runDeduplicationForSyncedPlays($deduplicationService, $minDate, $maxDate, $processedPlayIds);

                Log::info('Completed BGG plays sync job (no missing games, per-page)', [
                    'user_id' => $this->userId,
                    'plays_synced' => count($processedPlayIds),
                ]);

                return;
            }

            // There are missing games – keep the payloads in cache and schedule board game sync
            // batches plus a separate import-phase job which will run after games are synced.
            $batchSize = (int) config('boardgamegeek.rate_limiting.max_ids_per_request', 20);
            $boardGameQueue = config('boardgamegeek.board_game_sync_queue', 'default');
            $delayMinutes = (int) config('boardgamegeek.import_phase_delay_minutes', 10);

            // Schedule import phase first so it is always queued even if batch dispatch fails
            $importQueue = $this->queue ?? 'default';
            self::dispatch($this->userId, $minDate, $maxDate, $this->requestedManually, true)
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

            Log::info('BGG plays sync: scheduled board game batches and import phase (per-page)', [
                'user_id' => $this->userId,
                'missing_bgg_ids_count' => count($missingBggIds),
                'batches' => count($batches),
            ]);
        } catch (\Exception $e) {
            Log::error('BGG plays sync job failed', [
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts(),
            ]);
            if (isset($playsSync) && $playsSync !== null) {
                $playsSync->update([
                    'status' => BggPlaysSync::STATUS_FAILED,
                    'error_message' => substr($e->getMessage(), 0, 500),
                ]);
            } elseif (isset($syncId)) {
                BggPlaysSync::where('id', $syncId)->update([
                    'status' => BggPlaysSync::STATUS_FAILED,
                    'error_message' => substr($e->getMessage(), 0, 500),
                ]);
            }
            throw $e;
        }
    }

    /**
     * Build play payloads from BGG play elements (valid plays only).
     *
     * @param array<SimpleXMLElement> $plays
     * @return array<int, array<string, mixed>>
     */
    private function buildPayloadsFromPlays(array $plays, User $user, BoardGameGeekPlaySyncService $syncService): array
    {
        $payloads = [];
        foreach ($plays as $playElement) {
            if (! $syncService->validateBggPlay($playElement)) {
                continue;
            }
            $payloads[] = $syncService->buildPlayPayloadFromBggXml($playElement, $user);
        }
        return $payloads;
    }

    private function runImportPhase(
        User $user,
        BoardGameGeekPlaySyncService $syncService,
        BoardGamePlayDeduplicationService $deduplicationService
    ): void {
        $cacheKey = self::CACHE_KEY_PREFIX . $this->userId;
        $cached = Cache::get($cacheKey);
        if ($cached === null || ! isset($cached['payloads'], $cached['min_date'], $cached['max_date'], $cached['sync_id'])) {
            Log::warning('BGG plays import phase: no pending cache found', ['user_id' => $this->userId]);
            return;
        }

        $payloads = $cached['payloads'];
        $minDate = $cached['min_date'];
        $maxDate = $cached['max_date'];
        $syncId = (int) $cached['sync_id'];

        $processedPlayIds = [];
        foreach ($payloads as $payload) {
            $bggGameId = $payload['bgg_game_id'] ?? null;
            if ($bggGameId === null || $bggGameId === '') {
                continue;
            }
            $boardGame = BoardGame::where('bgg_id', (string) $bggGameId)->first();
            if ($boardGame === null || $boardGame->is_expansion) {
                continue;
            }
            $play = $syncService->createPlayFromPayload($payload, $user, $boardGame->id);
            $processedPlayIds[] = $play->bgg_play_id;
        }

        $allBggPlayIdsFromBgg = array_values(array_filter(array_column($payloads, 'bgg_play_id')));
        $syncService->cleanupDeletedBggPlays($user, $allBggPlayIdsFromBgg, $minDate, $maxDate);
        $this->markPlaysSynced($user, $processedPlayIds);

        BggPlaysSync::where('id', $syncId)->update([
            'status' => BggPlaysSync::STATUS_SUCCESS,
            'plays_count' => count($processedPlayIds),
            'error_message' => null,
        ]);

        Cache::forget($cacheKey);

        $this->runDeduplicationForSyncedPlays($deduplicationService, $minDate, $maxDate, $processedPlayIds);

        Log::info('BGG plays import phase completed', [
            'user_id' => $this->userId,
            'plays_imported' => count($processedPlayIds),
        ]);
    }

    private function markPlaysSynced(User $user, array $processedPlayIds): void
    {
        if ($processedPlayIds === []) {
            return;
        }
        BoardGamePlay::where('created_by_user_id', $user->id)
            ->where('source', 'boardgamegeek')
            ->whereIn('bgg_play_id', $processedPlayIds)
            ->update([
                'bgg_synced_at' => now(),
                'bgg_sync_status' => 'synced',
                'bgg_sync_error_message' => null,
            ]);
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

        BggPlaysSync::where('user_id', $this->userId)
            ->where('status', BggPlaysSync::STATUS_PENDING)
            ->orderByDesc('id')
            ->limit(1)
            ->update([
                'status' => BggPlaysSync::STATUS_FAILED,
                'error_message' => substr($exception->getMessage(), 0, 500),
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

