<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BoardGame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for BoardGameController API endpoints.
 *
 * These tests verify that the read-only board game API endpoints work correctly,
 * including listing board games and retrieving individual board games.
 */
class BoardGameControllerTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that the index endpoint returns a list of board games.
     */
    public function test_index_returns_list_of_board_games(): void
    {
        BoardGame::factory()->count(5)->create();

        $response = $this->getJson('/api/v1/board-games');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'min_players',
                        'max_players',
                        'playing_time_minutes',
                        'year_published',
                        'publisher',
                        'designer',
                        'image_url',
                        'bgg_id',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);
    }

    /**
     * Test that the index endpoint returns paginated results.
     */
    public function test_index_returns_paginated_results(): void
    {
        BoardGame::factory()->count(25)->create();

        $response = $this->getJson('/api/v1/board-games?per_page=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data',
                'links',
                'meta',
            ]);

        $data = $response->json('data');
        $this->assertCount(10, $data);
    }

    /**
     * Test that the index endpoint respects per_page limit.
     */
    public function test_index_respects_per_page_limit(): void
    {
        BoardGame::factory()->count(150)->create();

        $response = $this->getJson('/api/v1/board-games?per_page=200');

        $response->assertStatus(200);
        $data = $response->json('data');
        // Should be limited to 100 (max per_page)
        $this->assertLessThanOrEqual(100, count($data));
    }

    /**
     * Test that the show endpoint returns a single board game.
     */
    public function test_show_returns_single_board_game(): void
    {
        $boardGame = BoardGame::factory()->create([
            'name' => 'Test Game',
            'min_players' => 2,
            'max_players' => 4,
        ]);

        $response = $this->getJson("/api/v1/board-games/{$boardGame->id}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'description',
                    'min_players',
                    'max_players',
                    'playing_time_minutes',
                    'year_published',
                    'publisher',
                    'designer',
                    'image_url',
                    'bgg_id',
                    'created_at',
                    'updated_at',
                ],
            ])
            ->assertJson([
                'data' => [
                    'id' => $boardGame->id,
                    'name' => 'Test Game',
                    'min_players' => 2,
                    'max_players' => 4,
                ],
            ]);
    }

    /**
     * Test that the show endpoint returns 404 for non-existent board game.
     */
    public function test_show_returns_404_for_non_existent_board_game(): void
    {
        $response = $this->getJson('/api/v1/board-games/99999');

        $response->assertStatus(404)
            ->assertJson([
                'message' => 'Board game not found',
            ]);
    }

    /**
     * Test that write operations are not available (POST should return 405).
     */
    public function test_store_endpoint_is_not_available(): void
    {
        $response = $this->postJson('/api/v1/board-games', [
            'name' => 'New Game',
            'min_players' => 2,
            'max_players' => 4,
        ]);

        $response->assertStatus(405);
    }

    /**
     * Test that update operations are not available (PUT should return 405).
     */
    public function test_update_endpoint_is_not_available(): void
    {
        $boardGame = BoardGame::factory()->create();

        $response = $this->putJson("/api/v1/board-games/{$boardGame->id}", [
            'name' => 'Updated Game',
        ]);

        $response->assertStatus(405);
    }

    /**
     * Test that delete operations are not available (DELETE should return 405).
     */
    public function test_destroy_endpoint_is_not_available(): void
    {
        $boardGame = BoardGame::factory()->create();

        $response = $this->deleteJson("/api/v1/board-games/{$boardGame->id}");

        $response->assertStatus(405);
    }
}
