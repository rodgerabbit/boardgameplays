<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Jobs\SyncBoardGamePlaysFromBoardGameGeekJob;
use App\Jobs\SyncBoardGamePlayToBoardGameGeekJob;
use App\Models\BoardGame;
use App\Models\BoardGamePlay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
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
}

