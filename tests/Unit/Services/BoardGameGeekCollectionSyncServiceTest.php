<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Jobs\SyncBoardGameFromBoardGameGeekJob;
use App\Models\BoardGame;
use App\Models\BggCollectionItem;
use App\Models\BggCollectionItemChange;
use App\Models\BggCollectionSync;
use App\Models\User;
use App\Services\BoardGameGeekApiClient;
use App\Services\BoardGameGeekCollectionSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class BoardGameGeekCollectionSyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private BoardGameGeekCollectionSyncService $service;

    private BoardGameGeekApiClient $apiClient;

    protected function setUp(): void
    {
        parent::setUp();
        $this->apiClient = $this->createMock(BoardGameGeekApiClient::class);
        $this->service = new BoardGameGeekCollectionSyncService($this->apiClient);
    }

    public function test_sync_collection_for_user_with_no_bgg_username_does_nothing(): void
    {
        $user = User::factory()->create(['board_game_geek_username' => null]);
        $this->apiClient->expects($this->never())->method('fetchCollection');
        $this->service->syncCollectionForUser($user);
        $this->assertDatabaseCount('bgg_collection_syncs', 0);
    }

    public function test_sync_collection_fetches_and_upserts_items_and_records_changes(): void
    {
        Queue::fake();
        $user = User::factory()->create(['board_game_geek_username' => 'bgguser']);
        $boardGame = BoardGame::factory()->create(['bgg_id' => '13']);

        $collectionItems = [
            [
                'objectid' => '13',
                'name' => 'Catan',
                'yearpublished' => 1995,
                'image' => null,
                'thumbnail' => 'https://example.com/thumb.jpg',
                'user_rating' => 8.0,
                'owned' => true,
                'bgg_base_game_id' => null,
            ],
        ];

        $this->apiClient->method('fetchCollection')
            ->with('bgguser')
            ->willReturn($collectionItems);
        $this->apiClient->method('fetchBaseGameIdsForThingIds')
            ->willReturn(['13' => '13']);

        $this->service->syncCollectionForUser($user);

        $sync = BggCollectionSync::where('user_id', $user->id)->first();
        $this->assertNotNull($sync);
        $this->assertEquals(BggCollectionSync::STATUS_SUCCESS, $sync->status);
        $this->assertEquals(1, $sync->items_count);

        $item = BggCollectionItem::where('user_id', $user->id)->first();
        $this->assertNotNull($item);
        $this->assertEquals('13', $item->bgg_id);
        $this->assertEquals('13', $item->bgg_object_id);
        $this->assertEquals($boardGame->id, $item->board_game_id);
        $this->assertEquals('Catan', $item->name);
        $this->assertEquals(8.0, (float) $item->user_rating);

        $change = BggCollectionItemChange::where('user_id', $user->id)->first();
        $this->assertNotNull($change);
        $this->assertEquals(BggCollectionItemChange::CHANGE_TYPE_ADDED, $change->change_type);
    }

    public function test_sync_collection_dispatches_board_game_sync_for_missing_games(): void
    {
        Queue::fake();
        $user = User::factory()->create(['board_game_geek_username' => 'bgguser']);
        $collectionItems = [
            [
                'objectid' => '999',
                'name' => 'New Game',
                'yearpublished' => 2020,
                'image' => null,
                'thumbnail' => null,
                'user_rating' => null,
                'owned' => true,
                'bgg_base_game_id' => null,
            ],
        ];
        $this->apiClient->method('fetchCollection')->willReturn($collectionItems);
        $this->apiClient->method('fetchBaseGameIdsForThingIds')->willReturn(['999' => '999']);

        $this->service->syncCollectionForUser($user);

        Queue::assertPushed(SyncBoardGameFromBoardGameGeekJob::class, function ($job) {
            return $job->bggId === '999';
        });
    }
}
