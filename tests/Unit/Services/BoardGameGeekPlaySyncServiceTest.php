<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\BoardGame;
use App\Models\BoardGamePlay;
use App\Models\User;
use App\Services\BoardGameGeekPlaySyncService;
use App\Services\BoardGameGeekSyncService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use SimpleXMLElement;
use Tests\TestCase;

/**
 * Unit tests for BoardGameGeekPlaySyncService.
 *
 * These tests verify play sync behavior, including inline board game sync when board game is missing.
 */
class BoardGameGeekPlaySyncServiceTest extends TestCase
{
    use RefreshDatabase;

    private BoardGameGeekPlaySyncService $syncService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->syncService = $this->app->make(BoardGameGeekPlaySyncService::class);
    }

    public function test_sync_play_from_bgg_xml_returns_null_when_board_game_missing_and_inline_sync_fails(): void
    {
        $syncServiceMock = $this->createMock(BoardGameGeekSyncService::class);
        $syncServiceMock->method('syncBoardGameByBggId')
            ->with('224517')
            ->willThrowException(new \RuntimeException('BGG API unavailable'));
        $syncService = new BoardGameGeekPlaySyncService($syncServiceMock);

        $user = User::factory()->create(['board_game_geek_username' => 'testuser', 'default_group_id' => null]);
        $playXml = $this->createMinimalPlayXml('12345', '224517', '2025-01-15');

        $result = $syncService->syncPlayFromBggXml($playXml, $user);

        $this->assertNull($result);
        $this->assertDatabaseMissing('board_game_plays', ['bgg_play_id' => '12345']);
    }

    public function test_sync_play_from_bgg_xml_creates_play_when_board_game_exists(): void
    {
        $user = User::factory()->create(['board_game_geek_username' => 'testuser', 'default_group_id' => null]);
        $boardGame = BoardGame::factory()->create(['bgg_id' => '224517', 'is_expansion' => false]);
        $playXml = $this->createMinimalPlayXml('12345', '224517', '2025-01-15');

        $result = $this->syncService->syncPlayFromBggXml($playXml, $user);

        $this->assertInstanceOf(BoardGamePlay::class, $result);
        $this->assertEquals('12345', $result->bgg_play_id);
        $this->assertEquals($boardGame->id, $result->board_game_id);
        $this->assertEquals($user->id, $result->created_by_user_id);
    }

    public function test_process_bgg_plays_xml_does_not_add_to_processed_when_inline_board_game_sync_fails(): void
    {
        $syncServiceMock = $this->createMock(BoardGameGeekSyncService::class);
        $syncServiceMock->method('syncBoardGameByBggId')
            ->with('224517')
            ->willThrowException(new \RuntimeException('BGG API unavailable'));
        $syncService = new BoardGameGeekPlaySyncService($syncServiceMock);

        $user = User::factory()->create(['default_group_id' => null]);
        $playXml = $this->createMinimalPlayXml('12345', '224517', '2025-01-15');
        $plays = [$playXml];

        $processedPlayIds = $syncService->processBggPlaysXml($plays, $user);

        $this->assertSame([], $processedPlayIds);
    }

    public function test_process_bgg_plays_xml_adds_to_processed_when_play_synced(): void
    {
        $user = User::factory()->create(['default_group_id' => null]);
        BoardGame::factory()->create(['bgg_id' => '224517', 'is_expansion' => false]);
        $playXml = $this->createMinimalPlayXml('12345', '224517', '2025-01-15');
        $plays = [$playXml];

        $processedPlayIds = $this->syncService->processBggPlaysXml($plays, $user);

        $this->assertSame(['12345'], $processedPlayIds);
    }

    public function test_build_play_payload_from_bgg_xml_returns_serializable_array(): void
    {
        $user = User::factory()->create(['default_group_id' => null]);
        $playXml = $this->createMinimalPlayXml('12345', '224517', '2025-01-15');

        $payload = $this->syncService->buildPlayPayloadFromBggXml($playXml, $user);

        $this->assertIsArray($payload);
        $this->assertSame('12345', $payload['bgg_play_id']);
        $this->assertSame('224517', $payload['bgg_game_id']);
        $this->assertSame('2025-01-15', $payload['played_at']);
        $this->assertSame('boardgamegeek', $payload['source']);
        $this->assertArrayHasKey('players', $payload);
    }

    public function test_create_play_from_payload_creates_play_and_players(): void
    {
        $user = User::factory()->create(['default_group_id' => null]);
        $boardGame = BoardGame::factory()->create(['bgg_id' => '224517', 'is_expansion' => false]);
        $playPayload = [
            'bgg_play_id' => '98765',
            'bgg_game_id' => '224517',
            'played_at' => '2025-01-15',
            'location' => 'Home',
            'comment' => null,
            'game_length_minutes' => 60,
            'is_incomplete' => false,
            'group_id' => null,
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

        $play = $this->syncService->createPlayFromPayload($playPayload, $user, $boardGame->id);

        $this->assertInstanceOf(BoardGamePlay::class, $play);
        $this->assertEquals('98765', $play->bgg_play_id);
        $this->assertEquals($boardGame->id, $play->board_game_id);
        $this->assertCount(1, $play->players);
    }

    /**
     * Create minimal valid BGG play XML for testing.
     */
    private function createMinimalPlayXml(string $playId, string $gameId, string $date): SimpleXMLElement
    {
        $xml = <<<XML
<?xml version="1.0"?>
<plays>
  <play id="{$playId}" date="{$date}" quantity="1" length="60" incomplete="0" nowinstats="0" location="Home">
    <comments></comments>
    <item objectid="{$gameId}" name="Test Game">
      <subtypes>
        <subtype value="boardgame"/>
      </subtypes>
    </item>
    <players>
      <player username="testuser" name="Test User" win="1" score="100" startposition="1" new="0"/>
    </players>
  </play>
</plays>
XML;

        $plays = new SimpleXMLElement($xml);
        $playElements = $plays->play ?? [];
        $first = $playElements[0] ?? null;
        $this->assertNotNull($first, 'Test XML must contain one play element');

        return $first;
    }
}
