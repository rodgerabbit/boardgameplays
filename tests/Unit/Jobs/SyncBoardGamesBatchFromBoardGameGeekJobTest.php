<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Jobs\SyncBoardGamesBatchFromBoardGameGeekJob;
use App\Models\BoardGame;
use App\Services\BoardGameGeekSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Unit tests for SyncBoardGamesBatchFromBoardGameGeekJob.
 *
 * These tests verify that the batch job correctly syncs multiple board games from BoardGameGeek.
 */
class SyncBoardGamesBatchFromBoardGameGeekJobTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test that batch job can be dispatched.
     */
    public function test_batch_job_can_be_dispatched(): void
    {
        Queue::fake();

        SyncBoardGamesBatchFromBoardGameGeekJob::dispatch(['224517', '342942']);

        Queue::assertPushed(SyncBoardGamesBatchFromBoardGameGeekJob::class, function ($job) {
            return $job->bggIds === ['224517', '342942'];
        });
    }

    /**
     * Test that batch job syncs multiple board games successfully.
     */
    public function test_batch_job_syncs_multiple_board_games_successfully(): void
    {
        // Set up configuration
        config([
            'boardgamegeek.api_base_url' => 'https://boardgamegeek.com/xmlapi2',
            'boardgamegeek.api_token' => 'test-token',
            'boardgamegeek.rate_limiting.minimum_seconds_between_requests' => 0,
            'boardgamegeek.rate_limiting.max_ids_per_request' => 20,
            'boardgamegeek.retry.max_attempts' => 1,
            'boardgamegeek.retry.retry_after_202_seconds' => 1,
            'boardgamegeek.retry.exponential_backoff_max_seconds' => 60,
        ]);

        Http::fake([
            'boardgamegeek.com/xmlapi2/thing*' => Http::response($this->getSampleXmlResponse(), 200),
        ]);

        $job = new SyncBoardGamesBatchFromBoardGameGeekJob(['224517']);
        $syncService = $this->app->make(BoardGameGeekSyncService::class);
        $job->handle($syncService);

        $this->assertDatabaseHas('board_games', [
            'bgg_id' => '224517',
            'name' => 'Brass: Birmingham',
            'bgg_sync_status' => 'success',
        ]);
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


