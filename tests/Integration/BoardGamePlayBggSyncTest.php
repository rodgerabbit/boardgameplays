<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Jobs\SyncBoardGamePlaysFromBoardGameGeekJob;
use App\Jobs\SyncBoardGamePlayToBoardGameGeekJob;
use App\Models\BggPlaysSync;
use App\Models\BoardGame;
use App\Models\BoardGamePlay;
use App\Models\Group;
use App\Models\User;
use App\Services\BoardGameGeekPlaySyncService;
use App\Services\BoardGamePlayDeduplicationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\MockObject\MockObject;
use Tests\TestCase;

/**
 * Integration tests for BGG play sync (mocked).
 *
 * These tests verify BGG sync functionality with mocked API calls.
 */
class BoardGamePlayBggSyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_play_to_bgg_job_is_queued_when_sync_requested(): void
    {
        Queue::fake();

        $user = User::factory()->create();
        $boardGame = BoardGame::factory()->create(['is_expansion' => false, 'bgg_id' => '12345']);
        $play = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'created_by_user_id' => $user->id,
            'sync_to_bgg' => true,
        ]);

        SyncBoardGamePlayToBoardGameGeekJob::dispatch($play->id);

        Queue::assertPushed(SyncBoardGamePlayToBoardGameGeekJob::class);
    }

    public function test_sync_plays_from_bgg_job_is_queued(): void
    {
        Queue::fake();

        $user = User::factory()->create(['board_game_geek_username' => 'testuser']);

        SyncBoardGamePlaysFromBoardGameGeekJob::dispatch($user->id);

        Queue::assertPushed(SyncBoardGamePlaysFromBoardGameGeekJob::class);
    }

    public function test_bgg_play_submission_service_handles_login(): void
    {
        Http::fake([
            'boardgamegeek.com/login/api/v1' => Http::response([], 200, ['Set-Cookie' => 'session=abc123']),
        ]);

        $service = new \App\Services\BoardGameGeekPlaySubmissionService();

        $credentials = $service->loginToBoardGameGeek('testuser', 'testpass');

        $this->assertArrayHasKey('cookies', $credentials);
        Http::assertSent(function ($request) {
            return $request->url() === 'https://boardgamegeek.com/login/api/v1'
                && $request->method() === 'POST';
        });
    }

    public function test_sync_plays_from_bgg_job_runs_deduplication_for_affected_groups_and_date_range(): void
    {
        $group = Group::factory()->create();
        $user = User::factory()->create([
            'board_game_geek_username' => 'bgguser',
            'default_group_id' => $group->id,
        ]);
        $boardGame = BoardGame::factory()->create(['is_expansion' => false]);
        BoardGamePlay::factory()->create([
            'created_by_user_id' => $user->id,
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'source' => 'boardgamegeek',
            'bgg_play_id' => '999888',
        ]);

        $minDate = '2025-01-05';
        $maxDate = '2025-01-10';
        $processedPlayIds = ['999888'];

        $syncService = $this->createMockSyncService($processedPlayIds);
        $deduplicationService = $this->createMockDeduplicationServiceExpectingGroupAndDateRange(
            $group->id,
            $minDate,
            $maxDate
        );

        $job = new SyncBoardGamePlaysFromBoardGameGeekJob($user->id, $minDate, $maxDate);
        $job->handle($syncService, $deduplicationService);

        $this->addToAssertionCount(1);
    }

    public function test_sync_plays_from_bgg_job_does_not_run_deduplication_when_no_plays_processed(): void
    {
        $user = User::factory()->create([
            'board_game_geek_username' => 'bgguser',
        ]);

        $syncService = $this->createMockSyncService([]);
        $deduplicationService = $this->createMock(BoardGamePlayDeduplicationService::class);
        $deduplicationService->expects($this->never())
            ->method('syncDeduplicationForGroupAndDateRange');

        $job = new SyncBoardGamePlaysFromBoardGameGeekJob($user->id, '2025-01-01', '2025-01-15');
        $job->handle($syncService, $deduplicationService);

        $this->addToAssertionCount(1);
    }

    public function test_sync_plays_from_bgg_job_creates_and_updates_bgg_plays_sync_record(): void
    {
        $user = User::factory()->create([
            'board_game_geek_username' => 'bgguser',
        ]);

        $syncService = $this->createMockSyncService(['play1', 'play2']);
        $deduplicationService = $this->createMock(BoardGamePlayDeduplicationService::class);
        $deduplicationService->expects($this->any())->method('syncDeduplicationForGroupAndDateRange');

        $job = new SyncBoardGamePlaysFromBoardGameGeekJob($user->id, '2025-01-01', '2025-01-15', true);
        $job->handle($syncService, $deduplicationService);

        $playsSync = BggPlaysSync::where('user_id', $user->id)->latest('id')->first();
        $this->assertNotNull($playsSync);
        $this->assertSame(BggPlaysSync::STATUS_SUCCESS, $playsSync->status);
        $this->assertSame(2, $playsSync->plays_count);
        $this->assertTrue($playsSync->requested_manually);
    }

    /**
     * @return BoardGameGeekPlaySyncService&MockObject
     */
    private function createMockSyncService(array $processedPlayIds): BoardGameGeekPlaySyncService
    {
        $mock = $this->createMock(BoardGameGeekPlaySyncService::class);
        $mock->method('fetchPlaysFromBoardGameGeek')->willReturn([]);
        $mock->method('processBggPlaysXml')->willReturn($processedPlayIds);
        $mock->method('cleanupDeletedBggPlays')->willReturnCallback(function (): void {});

        return $mock;
    }

    /**
     * @return BoardGamePlayDeduplicationService&MockObject
     */
    private function createMockDeduplicationServiceExpectingGroupAndDateRange(
        int $expectedGroupId,
        string $expectedFrom,
        string $expectedTo
    ): BoardGamePlayDeduplicationService {
        $mock = $this->createMock(BoardGamePlayDeduplicationService::class);
        $mock->expects($this->once())
            ->method('syncDeduplicationForGroupAndDateRange')
            ->with(
                $this->identicalTo($expectedGroupId),
                $this->callback(function (Carbon $from) use ($expectedFrom): bool {
                    return $from->toDateString() === $expectedFrom;
                }),
                $this->callback(function (Carbon $to) use ($expectedTo): bool {
                    return $to->toDateString() === $expectedTo;
                })
            );

        return $mock;
    }
}

