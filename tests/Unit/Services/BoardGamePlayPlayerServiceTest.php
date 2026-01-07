<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\BoardGame;
use App\Models\BoardGamePlay;
use App\Models\User;
use App\Services\BoardGamePlayPlayerService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for BoardGamePlayPlayerService.
 *
 * These tests verify the service's logic for detecting new players.
 */
class BoardGamePlayPlayerServiceTest extends TestCase
{
    use RefreshDatabase;

    private BoardGamePlayPlayerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BoardGamePlayPlayerService();
    }

    public function test_is_first_play_for_player_returns_true_when_no_previous_plays(): void
    {
        $boardGame = BoardGame::factory()->create(['is_expansion' => false]);
        $user = User::factory()->create();

        $isFirst = $this->service->isFirstPlayForPlayer($boardGame, $user);

        $this->assertTrue($isFirst);
    }

    public function test_is_first_play_for_player_returns_false_when_previous_play_exists(): void
    {
        $boardGame = BoardGame::factory()->create(['is_expansion' => false]);
        $user = User::factory()->create();
        $play = BoardGamePlay::factory()->create(['board_game_id' => $boardGame->id]);
        \App\Models\BoardGamePlayPlayer::factory()->asUser($user)->create(['board_game_play_id' => $play->id]);

        $isFirst = $this->service->isFirstPlayForPlayer($boardGame, $user);

        $this->assertFalse($isFirst);
    }
}

