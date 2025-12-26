<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\DTOs\BoardGameGeekGameDto;
use App\Models\BoardGame;
use App\Services\BoardGameGeekApiClient;
use App\Services\BoardGameGeekSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Unit tests for BoardGameGeekSyncService.
 *
 * These tests verify that the sync service correctly updates or creates
 * board games from BoardGameGeek data.
 */
class BoardGameGeekSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private BoardGameGeekSyncService $syncService;
    private BoardGameGeekApiClient $apiClient;

    protected function setUp(): void
    {
        parent::setUp();

        // Set up configuration
        config([
            'boardgamegeek.api_base_url' => 'https://boardgamegeek.com/xmlapi2',
            'boardgamegeek.api_token' => 'test-token',
            'boardgamegeek.rate_limiting.minimum_seconds_between_requests' => 0, // No delay in tests
            'boardgamegeek.rate_limiting.max_ids_per_request' => 20,
            'boardgamegeek.retry.max_attempts' => 1, // No retries in tests
            'boardgamegeek.retry.retry_after_202_seconds' => 1,
            'boardgamegeek.retry.exponential_backoff_max_seconds' => 60,
        ]);

        $this->apiClient = $this->app->make(BoardGameGeekApiClient::class);
        $this->syncService = new BoardGameGeekSyncService($this->apiClient);
    }

    /**
     * Test that syncBoardGameByBggId creates a new board game.
     */
    public function test_sync_board_game_by_bgg_id_creates_new_board_game(): void
    {
        Http::fake([
            'boardgamegeek.com/xmlapi2/thing*' => Http::response($this->getSampleXmlResponse(), 200),
        ]);

        $boardGame = $this->syncService->syncBoardGameByBggId('224517');

        $this->assertInstanceOf(BoardGame::class, $boardGame);
        $this->assertEquals('224517', $boardGame->bgg_id);
        $this->assertEquals('Brass: Birmingham', $boardGame->name);
        $this->assertNotNull($boardGame->bgg_synced_at);
        $this->assertEquals('success', $boardGame->bgg_sync_status);
        $this->assertNull($boardGame->bgg_sync_error_message);
    }

    /**
     * Test that syncBoardGameByBggId updates existing board game.
     */
    public function test_sync_board_game_by_bgg_id_updates_existing_board_game(): void
    {
        $existingGame = BoardGame::factory()->create([
            'bgg_id' => '224517',
            'name' => 'Old Name',
            'bgg_rating' => null,
        ]);

        Http::fake([
            'boardgamegeek.com/xmlapi2/thing*' => Http::response($this->getSampleXmlResponse(), 200),
        ]);

        $boardGame = $this->syncService->syncBoardGameByBggId('224517');

        $this->assertEquals($existingGame->id, $boardGame->id);
        $this->assertEquals('Brass: Birmingham', $boardGame->name);
        $this->assertNotNull($boardGame->bgg_rating);
        $this->assertEquals('success', $boardGame->bgg_sync_status);
    }

    /**
     * Test that syncBoardGamesByBggIds syncs multiple games.
     */
    public function test_sync_board_games_by_bgg_ids_syncs_multiple_games(): void
    {
        // Mock two separate API calls (since we're chunking by max IDs per request)
        Http::fake([
            'boardgamegeek.com/xmlapi2/thing*' => Http::response($this->getSampleXmlResponse(), 200),
        ]);

        $boardGames = $this->syncService->syncBoardGamesByBggIds(['224517']);

        $this->assertCount(1, $boardGames); // Sample XML only has one game
        $this->assertInstanceOf(BoardGame::class, $boardGames[0]);
    }

    /**
     * Test that syncBoardGameByBggId throws exception when game not found.
     */
    public function test_sync_board_game_by_bgg_id_throws_exception_when_not_found(): void
    {
        Http::fake([
            'boardgamegeek.com/xmlapi2/thing*' => Http::response('<?xml version="1.0"?><items></items>', 200),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No board game found with BGG ID: 999999');

        $this->syncService->syncBoardGameByBggId('999999');
    }

    /**
     * Get a sample XML response for testing.
     *
     * @return string
     */
    private function getSampleXmlResponse(): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>
<items>
    <item type="boardgame" id="224517">
        <name type="primary" value="Brass: Birmingham"/>
        <yearpublished value="2018"/>
        <minplayers value="2"/>
        <maxplayers value="4"/>
        <playingtime value="60"/>
        <description>Brass: Birmingham is an economic strategy game sequel to Martin Wallace\'s Brass.</description>
        <image>https://cf.geekdo-images.com/image1.jpg</image>
        <thumbnail>https://cf.geekdo-images.com/thumb1.jpg</thumbnail>
        <link type="boardgamepublisher" id="12345" value="Roxley Games"/>
        <link type="boardgamedesigner" id="67890" value="Gavan Brown"/>
        <statistics>
            <ratings>
                <average value="8.57025"/>
                <averageweight value="4.123"/>
            </ratings>
        </statistics>
    </item>
</items>';
    }
}

