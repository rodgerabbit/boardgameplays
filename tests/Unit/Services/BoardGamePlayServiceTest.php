<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\BoardGame;
use App\Models\BoardGamePlay;
use App\Models\Group;
use App\Models\User;
use App\Services\BoardGamePlayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for BoardGamePlayService.
 *
 * These tests verify the service's business logic for creating, updating,
 * and validating board game plays.
 */
class BoardGamePlayServiceTest extends TestCase
{
    use RefreshDatabase;

    private BoardGamePlayService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(BoardGamePlayService::class);
    }

    public function test_create_board_game_play_creates_play_with_players(): void
    {
        $user = User::factory()->create();
        $boardGame = BoardGame::factory()->create(['is_expansion' => false]);
        $playerUser = User::factory()->create();

        $playData = [
            'board_game_id' => $boardGame->id,
            'played_at' => '2025-01-07',
            'location' => 'Home',
            'source' => 'website',
            'players' => [
                ['user_id' => $playerUser->id, 'is_winner' => true],
            ],
        ];

        $play = $this->service->createBoardGamePlay($playData, $user);

        $this->assertInstanceOf(BoardGamePlay::class, $play);
        $this->assertEquals($boardGame->id, $play->board_game_id);
        $this->assertEquals($user->id, $play->created_by_user_id);
        $this->assertCount(1, $play->players);
    }

    public function test_create_board_game_play_throws_exception_for_expansion(): void
    {
        $user = User::factory()->create();
        $expansion = BoardGame::factory()->create(['is_expansion' => true]);

        $playData = [
            'board_game_id' => $expansion->id,
            'played_at' => '2025-01-07',
            'location' => 'Home',
            'source' => 'website',
            'players' => [
                ['guest_name' => 'Player 1'],
            ],
        ];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Board game must not be an expansion');

        $this->service->createBoardGamePlay($playData, $user);
    }

    public function test_create_board_game_play_uses_default_group_when_not_provided(): void
    {
        $user = User::factory()->create();
        $group = Group::factory()->create();
        $user->update(['default_group_id' => $group->id]);

        $boardGame = BoardGame::factory()->create(['is_expansion' => false]);

        $playData = [
            'board_game_id' => $boardGame->id,
            'played_at' => '2025-01-07',
            'location' => 'Home',
            'source' => 'website',
            'players' => [
                ['guest_name' => 'Player 1'],
            ],
        ];

        $play = $this->service->createBoardGamePlay($playData, $user);

        $this->assertEquals($group->id, $play->group_id);
    }

    public function test_validate_player_count_throws_exception_for_too_many_players(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A play cannot have more than 30 players');

        $this->service->validatePlayerCount(31);
    }

    public function test_validate_player_count_throws_exception_for_zero_players(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('A play must have at least one player');

        $this->service->validatePlayerCount(0);
    }
}

