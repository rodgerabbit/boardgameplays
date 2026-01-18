<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\BoardGame;
use App\Models\BoardGamePlay;
use App\Models\BoardGamePlayPlayer;
use App\Models\Group;
use App\Models\User;
use App\Services\BoardGamePlayDeduplicationService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for BoardGamePlayDeduplicationService.
 *
 * These tests verify the deduplication logic for board game plays,
 * including duplicate detection, leading play selection, and exclusion handling.
 */
class BoardGamePlayDeduplicationServiceTest extends TestCase
{
    use RefreshDatabase;

    private BoardGamePlayDeduplicationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BoardGamePlayDeduplicationService();
    }

    public function test_finds_duplicate_plays_with_same_participants(): void
    {
        $group = Group::factory()->create();
        $boardGame = BoardGame::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $playerUser = User::factory()->create();

        $play1 = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user1->id,
            'played_at' => '2025-01-07',
        ]);

        $play2 = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user2->id,
            'played_at' => '2025-01-07',
        ]);

        // Add same players to both plays
        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play1->id,
            'user_id' => $playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play2->id,
            'user_id' => $playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);

        $this->service->syncDeduplicationForPlay($play1);

        $play1->refresh();
        $play2->refresh();

        // One should be leading, one should be excluded
        $leadingCount = 0;
        $excludedCount = 0;

        if ($play1->isLeading()) {
            $leadingCount++;
        } elseif ($play1->isExcluded()) {
            $excludedCount++;
        }

        if ($play2->isLeading()) {
            $leadingCount++;
        } elseif ($play2->isExcluded()) {
            $excludedCount++;
        }

        $this->assertEquals(1, $leadingCount, 'Exactly one play should be leading');
        $this->assertEquals(1, $excludedCount, 'Exactly one play should be excluded');
    }

    public function test_selects_earliest_created_play_as_leading(): void
    {
        $group = Group::factory()->create();
        $boardGame = BoardGame::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $playerUser = User::factory()->create();

        $earlierTime = Carbon::now()->subHour();
        $laterTime = Carbon::now();

        $play1 = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user1->id,
            'played_at' => '2025-01-07',
            'created_at' => $laterTime,
        ]);

        $play2 = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user2->id,
            'played_at' => '2025-01-07',
            'created_at' => $earlierTime,
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play1->id,
            'user_id' => $playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play2->id,
            'user_id' => $playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);

        $this->service->syncDeduplicationForPlay($play1);

        $play2->refresh();

        // Play2 (earlier created_at) should be leading
        $this->assertTrue($play2->isLeading(), 'Earlier created play should be leading');
        $this->assertTrue($play1->isExcluded(), 'Later created play should be excluded');
        $this->assertEquals($play2->id, $play1->leading_play_id);
    }

    public function test_selects_lower_bgg_play_id_when_created_at_is_same(): void
    {
        $group = Group::factory()->create();
        $boardGame = BoardGame::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $playerUser = User::factory()->create();

        $sameTime = Carbon::now();

        $play1 = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user1->id,
            'played_at' => '2025-01-07',
            'created_at' => $sameTime,
            'bgg_play_id' => '100',
        ]);

        $play2 = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user2->id,
            'played_at' => '2025-01-07',
            'created_at' => $sameTime,
            'bgg_play_id' => '50',
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play1->id,
            'user_id' => $playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play2->id,
            'user_id' => $playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);

        $this->service->syncDeduplicationForPlay($play1);

        $play2->refresh();

        // Play2 (lower bgg_play_id) should be leading
        $this->assertTrue($play2->isLeading(), 'Play with lower BGG ID should be leading');
        $this->assertTrue($play1->isExcluded(), 'Play with higher BGG ID should be excluded');
    }

    public function test_prefers_play_with_more_details_when_priority_is_equal(): void
    {
        $group = Group::factory()->create();
        $boardGame = BoardGame::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $playerUser = User::factory()->create();

        $sameTime = Carbon::now();

        $play1 = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user1->id,
            'played_at' => '2025-01-07',
            'created_at' => $sameTime,
            'comment' => 'Great game!',
            'game_length_minutes' => 90,
        ]);

        $play2 = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user2->id,
            'played_at' => '2025-01-07',
            'created_at' => $sameTime,
            'comment' => null,
            'game_length_minutes' => null,
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play1->id,
            'user_id' => $playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
            'score' => 100,
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play2->id,
            'user_id' => $playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
            'score' => null,
        ]);

        // Ensure both plays are persisted and have players loaded before syncing
        $play1->refresh();
        $play2->refresh();
        $play1->load('players');
        $play2->load('players');
        
        // Sync deduplication - this should mark one as leading and one as excluded
        $this->service->syncDeduplicationForPlay($play1);

        // Refresh both plays to get updated exclusion status
        $play1->refresh();
        $play2->refresh();
        $play1->load('players');
        $play2->load('players');

        // One play should be leading, one excluded
        $leadingPlay = $play1->isLeading() ? $play1 : $play2;
        $excludedPlay = $play1->isExcluded() ? $play1 : $play2;
        
        $this->assertTrue($leadingPlay->isLeading(), 'One play should be leading');
        $this->assertTrue($excludedPlay->isExcluded(), 'One play should be excluded');
        
        // Verify the play with more details is leading (play1 has comment, score, and game_length)
        $play1DetailScore = (!empty($play1->comment) ? 10 : 0) + 
                           ($play1->players->whereNotNull('score')->count() > 0 ? 5 : 0) +
                           ($play1->game_length_minutes !== null ? 2 : 0);
        $play2DetailScore = (!empty($play2->comment) ? 10 : 0) + 
                           ($play2->players->whereNotNull('score')->count() > 0 ? 5 : 0) +
                           ($play2->game_length_minutes !== null ? 2 : 0);
        
        // Play1 should have more details (comment + score + game_length = 17) vs play2 (0)
        $this->assertGreaterThan($play2DetailScore, $play1DetailScore, 'Play1 should have more details');
        
        // The leading play should be the one with more details when priority is equal
        // Since they have same created_at and no bgg_play_id, detail score should determine leading
        // Verify that the leading play has a detail score >= the excluded play's detail score
        $leadingDetailScore = (!empty($leadingPlay->comment) ? 10 : 0) + 
                             ($leadingPlay->players->whereNotNull('score')->count() > 0 ? 5 : 0) +
                             ($leadingPlay->game_length_minutes !== null ? 2 : 0);
        $excludedDetailScore = (!empty($excludedPlay->comment) ? 10 : 0) + 
                               ($excludedPlay->players->whereNotNull('score')->count() > 0 ? 5 : 0) +
                               ($excludedPlay->game_length_minutes !== null ? 2 : 0);
        
        // If play1 has more details, it should be leading
        if ($play1DetailScore > $play2DetailScore) {
            $this->assertEquals($play1->id, $leadingPlay->id, 'Play1 with more details should be leading');
            $this->assertEquals($play2->id, $excludedPlay->id, 'Play2 with fewer details should be excluded');
        } else {
            // If somehow play2 has more details, then it should be leading
            $this->assertEquals($play2->id, $leadingPlay->id, 'Play2 with more details should be leading');
            $this->assertEquals($play1->id, $excludedPlay->id, 'Play1 with fewer details should be excluded');
        }
    }

    public function test_does_not_mark_duplicates_if_different_participants(): void
    {
        $group = Group::factory()->create();
        $boardGame = BoardGame::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $playerUser1 = User::factory()->create();
        $playerUser2 = User::factory()->create();

        $play1 = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user1->id,
            'played_at' => '2025-01-07',
        ]);

        $play2 = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user2->id,
            'played_at' => '2025-01-07',
        ]);

        // Different players
        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play1->id,
            'user_id' => $playerUser1->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play2->id,
            'user_id' => $playerUser2->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);

        $this->service->syncDeduplicationForPlay($play1);

        $play1->refresh();
        $play2->refresh();

        // Neither should be excluded (different participants)
        $this->assertFalse($play1->isExcluded(), 'Play with different participants should not be excluded');
        $this->assertFalse($play2->isExcluded(), 'Play with different participants should not be excluded');
    }

    public function test_does_not_mark_duplicates_if_same_creator(): void
    {
        $group = Group::factory()->create();
        $boardGame = BoardGame::factory()->create();
        $user1 = User::factory()->create();
        $playerUser = User::factory()->create();

        $play1 = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user1->id,
            'played_at' => '2025-01-07',
        ]);

        $play2 = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user1->id, // Same creator
            'played_at' => '2025-01-07',
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play1->id,
            'user_id' => $playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play2->id,
            'user_id' => $playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);

        $this->service->syncDeduplicationForPlay($play1);

        $play1->refresh();
        $play2->refresh();

        // Neither should be excluded (same creator)
        $this->assertFalse($play1->isExcluded(), 'Plays with same creator should not be excluded');
        $this->assertFalse($play2->isExcluded(), 'Plays with same creator should not be excluded');
    }

    public function test_clears_exclusion_when_duplicate_no_longer_exists(): void
    {
        $group = Group::factory()->create();
        $boardGame = BoardGame::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $playerUser = User::factory()->create();

        $play1 = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user1->id,
            'played_at' => '2025-01-07',
            'created_at' => Carbon::now()->subHour(),
        ]);

        $play2 = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user2->id,
            'played_at' => '2025-01-07',
            'created_at' => Carbon::now(),
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play1->id,
            'user_id' => $playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play2->id,
            'user_id' => $playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);

        // Mark play2 as excluded
        $this->service->syncDeduplicationForPlay($play1);
        $play2->refresh();
        $this->assertTrue($play2->isExcluded());

        // Change play2's participants so it's no longer a duplicate
        $play2->players()->delete();
        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play2->id,
            'user_id' => User::factory()->create()->id, // Different player
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);

        // Re-sync - play2 should no longer be excluded
        $this->service->syncDeduplicationForPlay($play2);
        $play2->refresh();

        $this->assertFalse($play2->isExcluded(), 'Play should no longer be excluded when not a duplicate');
    }

    public function test_handles_plays_without_group(): void
    {
        $boardGame = BoardGame::factory()->create();
        $user = User::factory()->create();

        $play = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => null, // No group
            'created_by_user_id' => $user->id,
            'played_at' => '2025-01-07',
        ]);

        // Should not throw an error
        $this->service->syncDeduplicationForPlay($play);

        $play->refresh();
        $this->assertFalse($play->isExcluded(), 'Play without group should not be excluded');
    }

    public function test_handles_plays_with_different_dates(): void
    {
        $group = Group::factory()->create();
        $boardGame = BoardGame::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $playerUser = User::factory()->create();

        $play1 = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user1->id,
            'played_at' => '2025-01-07',
        ]);

        $play2 = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user2->id,
            'played_at' => '2025-01-08', // Different date
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play1->id,
            'user_id' => $playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play2->id,
            'user_id' => $playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);

        $this->service->syncDeduplicationForPlay($play1);

        $play1->refresh();
        $play2->refresh();

        // Neither should be excluded (different dates)
        $this->assertFalse($play1->isExcluded(), 'Plays with different dates should not be excluded');
        $this->assertFalse($play2->isExcluded(), 'Plays with different dates should not be excluded');
    }

    public function test_handles_plays_with_guest_players(): void
    {
        $group = Group::factory()->create();
        $boardGame = BoardGame::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $play1 = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user1->id,
            'played_at' => '2025-01-07',
        ]);

        $play2 = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user2->id,
            'played_at' => '2025-01-07',
        ]);

        // Same guest players
        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play1->id,
            'user_id' => null,
            'board_game_geek_username' => null,
            'guest_name' => 'Guest Player',
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play2->id,
            'user_id' => null,
            'board_game_geek_username' => null,
            'guest_name' => 'Guest Player',
        ]);

        $this->service->syncDeduplicationForPlay($play1);

        $play1->refresh();
        $play2->refresh();

        // One should be leading, one excluded
        $leadingCount = ($play1->isLeading() ? 1 : 0) + ($play2->isLeading() ? 1 : 0);
        $excludedCount = ($play1->isExcluded() ? 1 : 0) + ($play2->isExcluded() ? 1 : 0);

        $this->assertEquals(1, $leadingCount, 'Exactly one play should be leading');
        $this->assertEquals(1, $excludedCount, 'Exactly one play should be excluded');
    }
}
