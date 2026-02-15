<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\ImportBoardGamePlayFromBoardGameGeekJob;
use App\Models\BoardGame;
use App\Models\BoardGamePlay;
use App\Models\User;
use App\Services\BoardGameGeekPlaySyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Unit tests for ImportBoardGamePlayFromBoardGameGeekJob.
 *
 * These tests verify that the job imports a play after the board game has been synced.
 */
class ImportBoardGamePlayFromBoardGameGeekJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_job_can_be_dispatched(): void
    {
        Queue::fake();

        $playPayload = [
            'bgg_play_id' => '12345',
            'bgg_game_id' => '224517',
            'played_at' => '2025-01-15',
            'location' => 'Home',
            'comment' => null,
            'game_length_minutes' => 60,
            'is_incomplete' => false,
            'group_id' => null,
            'created_by_user_id' => 1,
            'source' => 'boardgamegeek',
            'players' => [],
        ];

        ImportBoardGamePlayFromBoardGameGeekJob::dispatch(1, $playPayload);

        Queue::assertPushed(ImportBoardGamePlayFromBoardGameGeekJob::class, function ($job) use ($playPayload) {
            return $job->userId === 1
                && $job->playPayload['bgg_play_id'] === $playPayload['bgg_play_id'];
        });
    }

    public function test_job_creates_play_when_board_game_exists(): void
    {
        $user = User::factory()->create(['default_group_id' => null]);
        $boardGame = BoardGame::factory()->create([
            'bgg_id' => '224517',
            'is_expansion' => false,
        ]);

        $playPayload = [
            'bgg_play_id' => '98765',
            'bgg_game_id' => '224517',
            'played_at' => '2025-01-15',
            'location' => 'Home',
            'comment' => null,
            'game_length_minutes' => 60,
            'is_incomplete' => false,
            'group_id' => $user->default_group_id,
            'created_by_user_id' => $user->id,
            'source' => 'boardgamegeek',
            'players' => [
                [
                    'user_id' => $user->id,
                    'board_game_geek_username' => null,
                    'guest_name' => null,
                    'is_winner' => true,
                    'is_new_player' => false,
                    'score' => 100.0,
                    'position' => 1,
                ],
            ],
        ];

        $job = new ImportBoardGamePlayFromBoardGameGeekJob($user->id, $playPayload);
        $syncService = $this->app->make(BoardGameGeekPlaySyncService::class);
        $job->handle($syncService);

        $this->assertDatabaseHas('board_game_plays', [
            'bgg_play_id' => '98765',
            'board_game_id' => $boardGame->id,
            'created_by_user_id' => $user->id,
            'source' => 'boardgamegeek',
        ]);

        $play = BoardGamePlay::where('bgg_play_id', '98765')->first();
        $this->assertNotNull($play);
        $this->assertCount(1, $play->players);
        $this->assertEquals($user->id, $play->players->first()->user_id);
        $this->assertTrue($play->players->first()->is_winner);
    }

    public function test_job_throws_when_board_game_not_yet_synced(): void
    {
        $user = User::factory()->create();
        $playPayload = [
            'bgg_play_id' => '98765',
            'bgg_game_id' => '999999',
            'played_at' => '2025-01-15',
            'location' => 'Home',
            'comment' => null,
            'game_length_minutes' => 60,
            'is_incomplete' => false,
            'group_id' => null,
            'created_by_user_id' => $user->id,
            'source' => 'boardgamegeek',
            'players' => [],
        ];

        $job = new ImportBoardGamePlayFromBoardGameGeekJob($user->id, $playPayload);
        $syncService = $this->app->make(BoardGameGeekPlaySyncService::class);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Board game with BGG ID 999999 not found');

        $job->handle($syncService);
    }

    public function test_job_skips_play_when_board_game_is_expansion(): void
    {
        $user = User::factory()->create();
        BoardGame::factory()->create([
            'bgg_id' => '224518',
            'is_expansion' => true,
        ]);

        $playPayload = [
            'bgg_play_id' => '98766',
            'bgg_game_id' => '224518',
            'played_at' => '2025-01-15',
            'location' => 'Home',
            'comment' => null,
            'game_length_minutes' => 60,
            'is_incomplete' => false,
            'group_id' => null,
            'created_by_user_id' => $user->id,
            'source' => 'boardgamegeek',
            'players' => [],
        ];

        $job = new ImportBoardGamePlayFromBoardGameGeekJob($user->id, $playPayload);
        $syncService = $this->app->make(BoardGameGeekPlaySyncService::class);
        $job->handle($syncService);

        $this->assertDatabaseMissing('board_game_plays', ['bgg_play_id' => '98766']);
    }

    public function test_job_does_nothing_when_user_not_found(): void
    {
        $playPayload = [
            'bgg_play_id' => '98765',
            'bgg_game_id' => '224517',
            'played_at' => '2025-01-15',
            'location' => 'Home',
            'comment' => null,
            'game_length_minutes' => 60,
            'is_incomplete' => false,
            'group_id' => null,
            'created_by_user_id' => 99999,
            'source' => 'boardgamegeek',
            'players' => [],
        ];

        $job = new ImportBoardGamePlayFromBoardGameGeekJob(99999, $playPayload);
        $syncService = $this->app->make(BoardGameGeekPlaySyncService::class);
        $job->handle($syncService);

        $this->assertDatabaseMissing('board_game_plays', ['bgg_play_id' => '98765']);
    }
}
