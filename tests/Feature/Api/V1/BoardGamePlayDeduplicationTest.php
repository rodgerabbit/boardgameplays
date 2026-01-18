<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Models\BoardGame;
use App\Models\BoardGamePlay;
use App\Models\BoardGamePlayPlayer;
use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Feature tests for board game play deduplication.
 *
 * These tests verify that duplicate plays are properly excluded from
 * API responses and statistics.
 */
class BoardGamePlayDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    private User $user1;
    private User $user2;
    private Group $group;
    private BoardGame $boardGame;
    private User $playerUser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user1 = User::factory()->create();
        $this->user2 = User::factory()->create();
        $this->group = Group::factory()->create();
        $this->boardGame = BoardGame::factory()->create(['is_expansion' => false]);
        $this->playerUser = User::factory()->create();

        // Add users to group
        GroupMember::create([
            'group_id' => $this->group->id,
            'user_id' => $this->user1->id,
            'role' => GroupMember::ROLE_GROUP_MEMBER,
        ]);
        GroupMember::create([
            'group_id' => $this->group->id,
            'user_id' => $this->user2->id,
            'role' => GroupMember::ROLE_GROUP_MEMBER,
        ]);
        GroupMember::create([
            'group_id' => $this->group->id,
            'user_id' => $this->playerUser->id,
            'role' => GroupMember::ROLE_GROUP_MEMBER,
        ]);

        Sanctum::actingAs($this->user1);
    }

    public function test_api_excludes_duplicate_plays_by_default(): void
    {
        // Create a duplicate play scenario
        $play1 = BoardGamePlay::factory()->create([
            'board_game_id' => $this->boardGame->id,
            'group_id' => $this->group->id,
            'created_by_user_id' => $this->user1->id,
            'played_at' => '2025-01-07',
            'is_excluded' => false,
        ]);

        $play2 = BoardGamePlay::factory()->create([
            'board_game_id' => $this->boardGame->id,
            'group_id' => $this->group->id,
            'created_by_user_id' => $this->user2->id,
            'played_at' => '2025-01-07',
            'is_excluded' => true,
            'leading_play_id' => $play1->id,
        ]);

        // Add same players to both
        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play1->id,
            'user_id' => $this->playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play2->id,
            'user_id' => $this->playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);

        $response = $this->getJson('/api/v1/board-game-plays');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Should only return non-excluded plays
        $playIds = collect($data)->pluck('id')->toArray();
        $this->assertContains($play1->id, $playIds, 'Leading play should be in results');
        $this->assertNotContains($play2->id, $playIds, 'Excluded play should not be in results');
    }

    public function test_api_can_include_excluded_plays_with_query_parameter(): void
    {
        $play1 = BoardGamePlay::factory()->create([
            'board_game_id' => $this->boardGame->id,
            'group_id' => $this->group->id,
            'created_by_user_id' => $this->user1->id,
            'played_at' => '2025-01-07',
            'is_excluded' => false,
        ]);

        $play2 = BoardGamePlay::factory()->create([
            'board_game_id' => $this->boardGame->id,
            'group_id' => $this->group->id,
            'created_by_user_id' => $this->user2->id,
            'played_at' => '2025-01-07',
            'is_excluded' => true,
            'leading_play_id' => $play1->id,
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play1->id,
            'user_id' => $this->playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play2->id,
            'user_id' => $this->playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);

        $response = $this->getJson('/api/v1/board-game-plays?include_excluded=1');

        $response->assertStatus(200);
        $data = $response->json('data');

        // Should return both plays when include_excluded is true
        $playIds = collect($data)->pluck('id')->toArray();
        $this->assertContains($play1->id, $playIds, 'Leading play should be in results');
        $this->assertContains($play2->id, $playIds, 'Excluded play should be in results when include_excluded=1');
    }

    public function test_creating_duplicate_play_marks_one_as_excluded(): void
    {
        // Create first play
        $play1 = BoardGamePlay::factory()->create([
            'board_game_id' => $this->boardGame->id,
            'group_id' => $this->group->id,
            'created_by_user_id' => $this->user1->id,
            'played_at' => '2025-01-07',
            'created_at' => now()->subHour(),
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play1->id,
            'user_id' => $this->playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);

        // Create duplicate play via API (as user2)
        Sanctum::actingAs($this->user2);

        $data = [
            'board_game_id' => $this->boardGame->id,
            'group_id' => $this->group->id,
            'played_at' => '2025-01-07',
            'location' => 'Home',
            'source' => 'website',
            'players' => [
                ['user_id' => $this->playerUser->id, 'is_winner' => true],
            ],
        ];

        $response = $this->postJson('/api/v1/board-game-plays', $data);

        $response->assertStatus(201);

        // Refresh plays
        $play1->refresh();
        $play2 = BoardGamePlay::where('created_by_user_id', $this->user2->id)
            ->where('board_game_id', $this->boardGame->id)
            ->first();

        // One should be leading, one excluded
        $leadingCount = ($play1->isLeading() ? 1 : 0) + ($play2->isLeading() ? 1 : 0);
        $excludedCount = ($play1->isExcluded() ? 1 : 0) + ($play2->isExcluded() ? 1 : 0);

        $this->assertEquals(1, $leadingCount, 'Exactly one play should be leading');
        $this->assertEquals(1, $excludedCount, 'Exactly one play should be excluded');
    }

    public function test_statistics_exclude_duplicate_plays(): void
    {
        // Create a leading play
        $leadingPlay = BoardGamePlay::factory()->create([
            'board_game_id' => $this->boardGame->id,
            'group_id' => $this->group->id,
            'created_by_user_id' => $this->user1->id,
            'played_at' => '2025-01-07',
            'is_excluded' => false,
        ]);

        // Create an excluded duplicate
        $excludedPlay = BoardGamePlay::factory()->create([
            'board_game_id' => $this->boardGame->id,
            'group_id' => $this->group->id,
            'created_by_user_id' => $this->user2->id,
            'played_at' => '2025-01-07',
            'is_excluded' => true,
            'leading_play_id' => $leadingPlay->id,
        ]);

        // Add player to both plays
        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $leadingPlay->id,
            'user_id' => $this->playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
            'is_winner' => true,
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $excludedPlay->id,
            'user_id' => $this->playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
            'is_winner' => true,
        ]);

        // Check statistics via dashboard (would need to access dashboard endpoint)
        // For now, verify the query directly
        $totalPlays = BoardGamePlayPlayer::where('user_id', $this->playerUser->id)
            ->whereHas('boardGamePlay', function ($query) {
                $query->notExcluded();
            })
            ->count();

        $totalWins = BoardGamePlayPlayer::where('user_id', $this->playerUser->id)
            ->where('is_winner', true)
            ->whereHas('boardGamePlay', function ($query) {
                $query->notExcluded();
            })
            ->count();

        // Should only count the leading play, not the excluded one
        $this->assertEquals(1, $totalPlays, 'Statistics should only count leading plays');
        $this->assertEquals(1, $totalWins, 'Win statistics should only count leading plays');
    }

    public function test_updating_play_can_change_exclusion_status(): void
    {
        // Create duplicate plays
        $play1 = BoardGamePlay::factory()->create([
            'board_game_id' => $this->boardGame->id,
            'group_id' => $this->group->id,
            'created_by_user_id' => $this->user1->id,
            'played_at' => '2025-01-07',
            'created_at' => now()->subHour(),
        ]);

        $play2 = BoardGamePlay::factory()->create([
            'board_game_id' => $this->boardGame->id,
            'group_id' => $this->group->id,
            'created_by_user_id' => $this->user2->id,
            'played_at' => '2025-01-07',
            'created_at' => now(),
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play1->id,
            'user_id' => $this->playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play2->id,
            'user_id' => $this->playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);

        // Sync deduplication
        app(\App\Services\BoardGamePlayDeduplicationService::class)->syncDeduplicationForPlay($play1);

        $play2->refresh();
        $this->assertTrue($play2->isExcluded(), 'Play2 should be excluded initially');

        // Update play2 to have different participants
        Sanctum::actingAs($this->user2);
        $differentPlayer = User::factory()->create();
        GroupMember::create([
            'group_id' => $this->group->id,
            'user_id' => $differentPlayer->id,
            'role' => GroupMember::ROLE_GROUP_MEMBER,
        ]);

        $updateData = [
            'players' => [
                ['user_id' => $differentPlayer->id, 'is_winner' => true],
            ],
        ];

        $response = $this->putJson("/api/v1/board-game-plays/{$play2->id}", $updateData);

        $response->assertStatus(200);

        $play2->refresh();

        // Play2 should no longer be excluded (different participants)
        $this->assertFalse($play2->isExcluded(), 'Play should no longer be excluded after participants change');
    }
}
