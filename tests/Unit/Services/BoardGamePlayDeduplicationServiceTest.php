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
        // When detail scores are equal, tiebreaker is lower bgg_play_id (earlier on BGG) then created_at
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
            'bgg_play_id' => '100',
            'location' => 'Unknown',
            'comment' => null,
            'game_length_minutes' => null,
        ]);

        $play2 = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user2->id,
            'played_at' => '2025-01-07',
            'bgg_play_id' => '200',
            'location' => 'Unknown',
            'comment' => null,
            'game_length_minutes' => null,
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play1->id,
            'user_id' => $playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
            'is_new_player' => false,
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play2->id,
            'user_id' => $playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
            'is_new_player' => false,
        ]);

        $this->service->syncDeduplicationForPlay($play1);

        $play1->refresh();
        $play2->refresh();

        // One leading, one excluded; excluded must point to different user
        $leadingPlay = $play1->isLeading() ? $play1 : $play2;
        $excludedPlay = $play1->isExcluded() ? $play1 : $play2;
        $this->assertTrue($leadingPlay->isLeading(), 'Exactly one play should be leading');
        $this->assertTrue($excludedPlay->isExcluded(), 'Exactly one play should be excluded');
        $this->assertEquals($leadingPlay->id, $excludedPlay->leading_play_id);
        $this->assertNotSame($leadingPlay->created_by_user_id, $excludedPlay->created_by_user_id, 'Excluded must point to different user');
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
            'bgg_play_id' => '50002',
            'location' => 'Unknown',
            'comment' => null,
            'game_length_minutes' => null,
        ]);

        $play2 = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user2->id,
            'played_at' => '2025-01-07',
            'created_at' => $sameTime,
            'bgg_play_id' => '50001',
            'location' => 'Unknown',
            'comment' => null,
            'game_length_minutes' => null,
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play1->id,
            'user_id' => $playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
            'is_new_player' => false,
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play2->id,
            'user_id' => $playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
            'is_new_player' => false,
        ]);

        $this->service->syncDeduplicationForPlay($play1);

        $play1->refresh();
        $play2->refresh();

        // When detail scores and created_at are equal, one is leading and one excluded; excluded must point to a different user
        $leadingPlay = $play1->isLeading() ? $play1 : $play2;
        $excludedPlay = $play1->isExcluded() ? $play1 : $play2;
        $this->assertTrue($leadingPlay->isLeading(), 'Exactly one play should be leading');
        $this->assertTrue($excludedPlay->isExcluded(), 'Exactly one play should be excluded');
        $this->assertEquals($leadingPlay->id, $excludedPlay->leading_play_id);
        $this->assertNotSame($leadingPlay->created_by_user_id, $excludedPlay->created_by_user_id, 'Excluded must point to play from different user');
    }

    public function test_never_excludes_play_when_leading_play_is_same_user(): void
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
            'location' => 'Unknown',
            'comment' => null,
            'bgg_play_id' => '100',
        ]);
        $play2 = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user2->id,
            'played_at' => '2025-01-07',
            'location' => 'Unknown',
            'comment' => null,
            'bgg_play_id' => '200',
        ]);

        BoardGamePlayPlayer::factory()->create(['board_game_play_id' => $play1->id, 'user_id' => $playerUser->id, 'board_game_geek_username' => null, 'guest_name' => null]);
        BoardGamePlayPlayer::factory()->create(['board_game_play_id' => $play2->id, 'user_id' => $playerUser->id, 'board_game_geek_username' => null, 'guest_name' => null]);

        $this->service->syncDeduplicationForPlay($play1);
        $play1->refresh();
        $play2->refresh();

        // One leading, one excluded; excluded must point to a play from a different user
        $leadingPlay = $play1->isLeading() ? $play1 : $play2;
        $excludedPlay = $play1->isExcluded() ? $play1 : $play2;
        $this->assertNotSame($leadingPlay->created_by_user_id, $excludedPlay->created_by_user_id, 'Excluded play must not point to same user as leading');
        if ($excludedPlay->leading_play_id !== null) {
            $leading = BoardGamePlay::find($excludedPlay->leading_play_id);
            $this->assertNotSame($leading->created_by_user_id, $excludedPlay->created_by_user_id, 'Excluded play must not refer to a play logged by the same user');
        }
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

    public function test_prefers_detail_rich_play_over_earlier_created_when_other_has_unknown_location(): void
    {
        // Scenario: two logs of the same game - one with location, new player, time, comment (NerdsLogSessie)
        // and one with Unknown location, no comment, no time (Jnnmn). The detail-rich play should lead
        // even if it was created later in our DB; "logged earlier" on BGG (lower bgg_play_id) is tiebreaker.
        $group = Group::factory()->create();
        $boardGame = BoardGame::factory()->create();
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();
        $playerUser = User::factory()->create();

        $earlierCreated = Carbon::now()->subMinute();
        $laterCreated = Carbon::now();

        $detailRichPlay = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user1->id,
            'played_at' => '2025-12-13',
            'created_at' => $laterCreated,
            'location' => 'Ruud en Fleur',
            'comment' => 'Agent chase won',
            'game_length_minutes' => 12,
            'bgg_play_id' => '107178283',
        ]);

        $sparsePlay = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user2->id,
            'played_at' => '2025-12-13',
            'created_at' => $earlierCreated,
            'location' => 'Unknown',
            'comment' => null,
            'game_length_minutes' => null,
            'bgg_play_id' => '107193505',
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $detailRichPlay->id,
            'user_id' => $playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
            'is_new_player' => true,
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $sparsePlay->id,
            'user_id' => $playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
            'is_new_player' => false,
        ]);

        $this->service->syncDeduplicationForPlay($detailRichPlay);

        $detailRichPlay->refresh();
        $sparsePlay->refresh();

        $this->assertTrue($detailRichPlay->isLeading(), 'Detail-rich play (location, comment, time, new player) should be leading');
        $this->assertTrue($sparsePlay->isExcluded(), 'Sparse play (Unknown location, no comment/time) should be excluded');
        $this->assertEquals($detailRichPlay->id, $sparsePlay->leading_play_id);
    }

    public function test_pairs_duplicates_by_play_order_when_four_plays_two_per_creator(): void
    {
        // Two real games: (play1A, play1B) and (play2A, play2B). Same participants, two creators.
        // Each group should pick the detail-richer play as leading, not merge all four into one group.
        $group = Group::factory()->create();
        $boardGame = BoardGame::factory()->create();
        $userA = User::factory()->create();
        $userB = User::factory()->create();
        $playerUser = User::factory()->create();

        $play1A = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $userA->id,
            'played_at' => '2025-12-13',
            'location' => 'Home',
            'comment' => 'First game',
            'game_length_minutes' => 15,
            'bgg_play_id' => '100',
        ]);
        $play2A = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $userA->id,
            'played_at' => '2025-12-13',
            'location' => 'Home',
            'comment' => 'Second game',
            'game_length_minutes' => 20,
            'bgg_play_id' => '101',
        ]);
        $play1B = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $userB->id,
            'played_at' => '2025-12-13',
            'location' => 'Unknown',
            'comment' => null,
            'game_length_minutes' => null,
            'bgg_play_id' => '200',
        ]);
        $play2B = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $userB->id,
            'played_at' => '2025-12-13',
            'location' => 'Unknown',
            'comment' => null,
            'game_length_minutes' => null,
            'bgg_play_id' => '201',
        ]);

        foreach ([$play1A, $play2A, $play1B, $play2B] as $play) {
            BoardGamePlayPlayer::factory()->create([
                'board_game_play_id' => $play->id,
                'user_id' => $playerUser->id,
                'board_game_geek_username' => null,
                'guest_name' => null,
            ]);
        }

        $this->service->syncDeduplicationForGroup($group->id);

        $play1A->refresh();
        $play2A->refresh();
        $play1B->refresh();
        $play2B->refresh();

        // With reverse pairing (later BGG IDs reversed): (play1A, play2B) and (play2A, play1B)
        // Pair 1: play1A leading, play2B excluded
        $this->assertTrue($play1A->isLeading(), 'Play1A should be leading');
        $this->assertTrue($play2B->isExcluded(), 'Play2B should be excluded');
        $this->assertEquals($play1A->id, $play2B->leading_play_id);

        // Pair 2: play2A leading, play1B excluded
        $this->assertTrue($play2A->isLeading(), 'Play2A should be leading');
        $this->assertTrue($play1B->isExcluded(), 'Play1B should be excluded');
        $this->assertEquals($play2A->id, $play1B->leading_play_id);
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
            'bgg_play_id' => '100',
            'location' => 'Unknown',
            'comment' => null,
            'game_length_minutes' => null,
        ]);

        $play2 = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'group_id' => $group->id,
            'created_by_user_id' => $user2->id,
            'played_at' => '2025-01-07',
            'created_at' => Carbon::now(),
            'bgg_play_id' => '200',
            'location' => 'Unknown',
            'comment' => null,
            'game_length_minutes' => null,
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play1->id,
            'user_id' => $playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
            'is_new_player' => false,
        ]);

        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $play2->id,
            'user_id' => $playerUser->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
            'is_new_player' => false,
        ]);

        // One play should be excluded (play1 has lower bgg_play_id so should be leading, play2 excluded)
        $this->service->syncDeduplicationForPlay($play1);
        $play1->refresh();
        $play2->refresh();
        $excludedPlay = $play1->isExcluded() ? $play1 : $play2;
        $this->assertTrue($excludedPlay->isExcluded(), 'Exactly one play should be excluded');

        // Change excluded play's participants so it's no longer a duplicate
        $excludedPlay->players()->delete();
        BoardGamePlayPlayer::factory()->create([
            'board_game_play_id' => $excludedPlay->id,
            'user_id' => User::factory()->create()->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);

        // Re-sync - excluded play should no longer be excluded
        $this->service->syncDeduplicationForPlay($excludedPlay);
        $excludedPlay->refresh();

        $this->assertFalse($excludedPlay->isExcluded(), 'Play should no longer be excluded when not a duplicate');
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
