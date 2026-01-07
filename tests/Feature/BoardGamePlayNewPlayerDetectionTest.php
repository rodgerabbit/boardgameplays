<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\BoardGame;
use App\Models\BoardGamePlay;
use App\Models\User;
use App\Services\BoardGamePlayService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for new player detection in board game plays.
 *
 * These tests verify that the system correctly detects and marks new players.
 */
class BoardGamePlayNewPlayerDetectionTest extends TestCase
{
    use RefreshDatabase;

    private BoardGamePlayService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BoardGamePlayService();
    }

    public function test_new_player_is_marked_when_first_play(): void
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
                ['user_id' => $playerUser->id],
            ],
        ];

        $play = $this->service->createBoardGamePlay($playData, $user);

        $player = $play->players->first();
        $this->assertTrue($player->is_new_player);
    }

    public function test_existing_player_is_not_marked_as_new(): void
    {
        $user = User::factory()->create();
        $boardGame = BoardGame::factory()->create(['is_expansion' => false]);
        $playerUser = User::factory()->create();

        // Create first play
        $firstPlayData = [
            'board_game_id' => $boardGame->id,
            'played_at' => '2025-01-06',
            'location' => 'Home',
            'source' => 'website',
            'players' => [
                ['user_id' => $playerUser->id],
            ],
        ];
        $this->service->createBoardGamePlay($firstPlayData, $user);

        // Create second play
        $secondPlayData = [
            'board_game_id' => $boardGame->id,
            'played_at' => '2025-01-07',
            'location' => 'Home',
            'source' => 'website',
            'players' => [
                ['user_id' => $playerUser->id],
            ],
        ];
        $secondPlay = $this->service->createBoardGamePlay($secondPlayData, $user);

        $player = $secondPlay->players->first();
        $this->assertFalse($player->is_new_player);
    }
}

