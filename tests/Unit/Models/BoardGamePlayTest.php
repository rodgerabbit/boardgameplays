<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\BoardGame;
use App\Models\BoardGamePlay;
use App\Models\BoardGamePlayPlayer;
use App\Models\Group;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for BoardGamePlay model.
 *
 * These tests verify the BoardGamePlay model's relationships, scopes, and methods.
 */
class BoardGamePlayTest extends TestCase
{
    use RefreshDatabase;

    public function test_board_game_play_belongs_to_board_game(): void
    {
        $boardGame = BoardGame::factory()->create(['is_expansion' => false]);
        $play = BoardGamePlay::factory()->create(['board_game_id' => $boardGame->id]);

        $this->assertInstanceOf(BoardGame::class, $play->boardGame);
        $this->assertEquals($boardGame->id, $play->boardGame->id);
    }

    public function test_board_game_play_belongs_to_group(): void
    {
        $group = Group::factory()->create();
        $play = BoardGamePlay::factory()->create(['group_id' => $group->id]);

        $this->assertInstanceOf(Group::class, $play->group);
        $this->assertEquals($group->id, $play->group->id);
    }

    public function test_board_game_play_belongs_to_creator(): void
    {
        $user = User::factory()->create();
        $play = BoardGamePlay::factory()->create(['created_by_user_id' => $user->id]);

        $this->assertInstanceOf(User::class, $play->creator);
        $this->assertEquals($user->id, $play->creator->id);
    }

    public function test_board_game_play_has_many_players(): void
    {
        $play = BoardGamePlay::factory()->create();
        BoardGamePlayPlayer::factory()->count(3)->create(['board_game_play_id' => $play->id]);

        $this->assertCount(3, $play->players);
    }

    public function test_board_game_play_has_many_expansions(): void
    {
        $play = BoardGamePlay::factory()->create();
        $expansion1 = BoardGame::factory()->create(['is_expansion' => true]);
        $expansion2 = BoardGame::factory()->create(['is_expansion' => true]);

        $play->expansions()->attach([$expansion1->id, $expansion2->id]);

        $this->assertCount(2, $play->expansions);
    }

    public function test_scope_for_group_filters_by_group(): void
    {
        $group1 = Group::factory()->create();
        $group2 = Group::factory()->create();

        $play1 = BoardGamePlay::factory()->create(['group_id' => $group1->id]);
        $play2 = BoardGamePlay::factory()->create(['group_id' => $group2->id]);

        $results = BoardGamePlay::forGroup($group1)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($play1->id, $results->first()->id);
    }

    public function test_scope_for_user_filters_by_creator(): void
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $play1 = BoardGamePlay::factory()->create(['created_by_user_id' => $user1->id]);
        $play2 = BoardGamePlay::factory()->create(['created_by_user_id' => $user2->id]);

        $results = BoardGamePlay::forUser($user1)->get();

        $this->assertCount(1, $results);
        $this->assertEquals($play1->id, $results->first()->id);
    }

    public function test_scope_from_source_filters_by_source(): void
    {
        BoardGamePlay::factory()->create(['source' => 'website']);
        BoardGamePlay::factory()->create(['source' => 'boardgamegeek']);

        $results = BoardGamePlay::fromSource('boardgamegeek')->get();

        $this->assertCount(1, $results);
        $this->assertEquals('boardgamegeek', $results->first()->source);
    }

    public function test_is_from_board_game_geek_returns_true_for_bgg_source(): void
    {
        $play = BoardGamePlay::factory()->create(['source' => 'boardgamegeek']);

        $this->assertTrue($play->isFromBoardGameGeek());
    }

    public function test_is_from_board_game_geek_returns_false_for_website_source(): void
    {
        $play = BoardGamePlay::factory()->create(['source' => 'website']);

        $this->assertFalse($play->isFromBoardGameGeek());
    }

    public function test_get_player_count_returns_correct_count(): void
    {
        $play = BoardGamePlay::factory()->create();
        BoardGamePlayPlayer::factory()->count(5)->create(['board_game_play_id' => $play->id]);

        $this->assertEquals(5, $play->getPlayerCount());
    }

    public function test_get_winners_returns_winning_players(): void
    {
        $play = BoardGamePlay::factory()->create();
        BoardGamePlayPlayer::factory()->create(['board_game_play_id' => $play->id, 'is_winner' => true]);
        BoardGamePlayPlayer::factory()->create(['board_game_play_id' => $play->id, 'is_winner' => false]);
        BoardGamePlayPlayer::factory()->create(['board_game_play_id' => $play->id, 'is_winner' => true]);

        $winners = $play->getWinners();

        $this->assertCount(2, $winners);
        $this->assertTrue($winners->every(fn ($player) => $player->is_winner));
    }
}

