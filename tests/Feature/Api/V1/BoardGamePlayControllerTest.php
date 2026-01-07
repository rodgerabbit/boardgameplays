<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BoardGame;
use App\Models\BoardGamePlay;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Feature tests for BoardGamePlayController.
 *
 * These tests verify the API endpoints for board game plays.
 */
class BoardGamePlayControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        Sanctum::actingAs($this->user);
    }

    public function test_index_returns_paginated_plays(): void
    {
        BoardGamePlay::factory()->count(5)->create(['created_by_user_id' => $this->user->id]);

        $response = $this->getJson('/api/v1/board-game-plays');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => ['id', 'board_game_id', 'played_at', 'location'],
                ],
                'links',
                'meta',
            ]);
    }

    public function test_store_creates_play_with_valid_data(): void
    {
        $boardGame = BoardGame::factory()->create(['is_expansion' => false]);
        $playerUser = User::factory()->create();

        $data = [
            'board_game_id' => $boardGame->id,
            'played_at' => '2025-01-07',
            'location' => 'Home',
            'source' => 'website',
            'players' => [
                ['user_id' => $playerUser->id, 'is_winner' => true],
            ],
        ];

        $response = $this->postJson('/api/v1/board-game-plays', $data);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => ['id', 'board_game_id', 'played_at', 'location'],
            ]);

        $this->assertDatabaseHas('board_game_plays', [
            'board_game_id' => $boardGame->id,
            'created_by_user_id' => $this->user->id,
        ]);
    }

    public function test_store_rejects_expansion_as_base_game(): void
    {
        $expansion = BoardGame::factory()->create(['is_expansion' => true]);

        $data = [
            'board_game_id' => $expansion->id,
            'played_at' => '2025-01-07',
            'location' => 'Home',
            'source' => 'website',
            'players' => [
                ['guest_name' => 'Player 1'],
            ],
        ];

        $response = $this->postJson('/api/v1/board-game-plays', $data);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['board_game_id']);
    }

    public function test_show_returns_play_details(): void
    {
        $play = BoardGamePlay::factory()->create(['created_by_user_id' => $this->user->id]);

        $response = $this->getJson("/api/v1/board-game-plays/{$play->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => ['id', 'board_game_id', 'played_at', 'location'],
            ]);
    }

    public function test_update_updates_play(): void
    {
        $play = BoardGamePlay::factory()->create(['created_by_user_id' => $this->user->id]);

        $data = [
            'location' => 'Updated Location',
        ];

        $response = $this->putJson("/api/v1/board-game-plays/{$play->id}", $data);

        $response->assertStatus(200);
        $this->assertDatabaseHas('board_game_plays', [
            'id' => $play->id,
            'location' => 'Updated Location',
        ]);
    }

    public function test_destroy_deletes_play(): void
    {
        $play = BoardGamePlay::factory()->create(['created_by_user_id' => $this->user->id]);

        $response = $this->deleteJson("/api/v1/board-game-plays/{$play->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('board_game_plays', ['id' => $play->id]);
    }
}

