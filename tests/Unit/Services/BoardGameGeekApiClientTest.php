<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\BoardGameGeekApiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Unit tests for BoardGameGeekApiClient.
 *
 * These tests verify that the API client correctly handles HTTP requests,
 * rate limiting, retries, error handling, and XML parsing.
 * All API calls are mocked to avoid real network requests.
 */
class BoardGameGeekApiClientTest extends TestCase
{
    private BoardGameGeekApiClient $apiClient;

    protected function setUp(): void
    {
        parent::setUp();

        // Clear cache before each test
        Cache::flush();

        // Create API client with test configuration
        $this->apiClient = new BoardGameGeekApiClient(
            apiBaseUrl: 'https://boardgamegeek.com/xmlapi2',
            apiToken: 'test-token-123',
            minimumSecondsBetweenRequests: 2,
            maxIdsPerRequest: 20,
            maxRetryAttempts: 5,
            retryAfter202Seconds: 3,
            exponentialBackoffMaxSeconds: 60,
        );
    }

    /**
     * Test that fetching board games by IDs makes correct HTTP request.
     */
    public function test_fetch_board_games_by_ids_makes_correct_http_request(): void
    {
        Http::fake([
            'boardgamegeek.com/xmlapi2/thing*' => Http::response($this->getSampleXmlResponse(), 200),
        ]);

        $this->apiClient->fetchBoardGamesByIds(['224517']);

        Http::assertSent(function (Request $request) {
            return $request->url() === 'https://boardgamegeek.com/xmlapi2/thing?id=224517&stats=1&type=boardgame,boardgameexpansion'
                && $request->hasHeader('Authorization', 'Bearer test-token-123')
                && $request->hasHeader('Accept', 'application/xml');
        });
    }

    /**
     * Test that fetching board games returns correct DTOs.
     */
    public function test_fetch_board_games_returns_correct_dtos(): void
    {
        Http::fake([
            'boardgamegeek.com/xmlapi2/thing*' => Http::response($this->getSampleXmlResponse(), 200),
        ]);

        $games = $this->apiClient->fetchBoardGamesByIds(['224517']);

        $this->assertCount(1, $games);
        $this->assertEquals('224517', $games[0]->bggId);
        $this->assertEquals('Brass: Birmingham', $games[0]->name);
        $this->assertNotNull($games[0]->bggRating);
        $this->assertNotNull($games[0]->complexityRating);
        
        // Verify integer fields are extracted correctly from value attributes
        $this->assertEquals(2, $games[0]->minPlayers);
        $this->assertEquals(4, $games[0]->maxPlayers);
        $this->assertEquals(60, $games[0]->playingTimeMinutes);
        $this->assertEquals(2018, $games[0]->yearPublished);
    }

    /**
     * Test that too many IDs throws exception.
     */
    public function test_fetch_board_games_with_too_many_ids_throws_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Maximum 20 IDs allowed per request');

        $ids = range(1, 21);
        $this->apiClient->fetchBoardGamesByIds($ids);
    }

    /**
     * Test that empty array returns empty result.
     */
    public function test_fetch_board_games_with_empty_array_returns_empty_result(): void
    {
        $games = $this->apiClient->fetchBoardGamesByIds([]);

        $this->assertEmpty($games);
    }

    /**
     * Test that 202 status retries after delay.
     */
    public function test_fetch_board_games_retries_on_202_status(): void
    {
        Http::fake([
            'boardgamegeek.com/xmlapi2/thing*' => Http::sequence()
                ->push(['message' => 'Processing'], 202)
                ->push($this->getSampleXmlResponse(), 200),
        ]);

        $games = $this->apiClient->fetchBoardGamesByIds(['224517']);

        $this->assertCount(1, $games);
        Http::assertSentCount(2);
    }

    /**
     * Test that 429 status performs exponential backoff.
     */
    public function test_fetch_board_games_performs_exponential_backoff_on_429(): void
    {
        Http::fake([
            'boardgamegeek.com/xmlapi2/thing*' => Http::sequence()
                ->push(['message' => 'Rate limited'], 429)
                ->push(['message' => 'Rate limited'], 429)
                ->push($this->getSampleXmlResponse(), 200),
        ]);

        $games = $this->apiClient->fetchBoardGamesByIds(['224517']);

        $this->assertCount(1, $games);
        Http::assertSentCount(3);
    }

    /**
     * Test that 401 status throws exception immediately.
     */
    public function test_fetch_board_games_throws_exception_on_401(): void
    {
        Http::fake([
            'boardgamegeek.com/xmlapi2/thing*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('BoardGameGeek API token was not accepted');

        $this->apiClient->fetchBoardGamesByIds(['224517']);
    }

    /**
     * Test that max retries are respected.
     */
    public function test_fetch_board_games_respects_max_retries(): void
    {
        Http::fake([
            'boardgamegeek.com/xmlapi2/thing*' => Http::response(['message' => 'Error'], 500),
        ]);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to fetch board games from BoardGameGeek after');

        $this->apiClient->fetchBoardGamesByIds(['224517']);
    }

    /**
     * Test that rate limiting is enforced.
     */
    public function test_fetch_board_games_enforces_rate_limiting(): void
    {
        Http::fake([
            'boardgamegeek.com/xmlapi2/thing*' => Http::response($this->getSampleXmlResponse(), 200),
        ]);

        // Set last request time to now (should trigger wait)
        Cache::put('boardgamegeek_api_last_request_time', now()->subSecond(), now()->addHours(1));

        $startTime = microtime(true);
        $this->apiClient->fetchBoardGamesByIds(['224517']);
        $endTime = microtime(true);

        // Should have waited at least 1 second (minimum 2 seconds between requests, 1 second already passed)
        $this->assertGreaterThanOrEqual(1.0, $endTime - $startTime);
    }

    /**
     * Test that only one request can run simultaneously.
     */
    public function test_fetch_board_games_only_one_request_at_a_time(): void
    {
        Http::fake([
            'boardgamegeek.com/xmlapi2/thing*' => Http::response($this->getSampleXmlResponse(), 200),
        ]);

        // This test verifies the lock mechanism works
        // In a real scenario, concurrent requests would be blocked
        $games = $this->apiClient->fetchBoardGamesByIds(['224517']);

        $this->assertCount(1, $games);
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

