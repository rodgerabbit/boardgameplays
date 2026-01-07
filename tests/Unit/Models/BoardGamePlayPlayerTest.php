<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\BoardGamePlay;
use App\Models\BoardGamePlayPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for BoardGamePlayPlayer model.
 *
 * These tests verify the BoardGamePlayPlayer model's methods and identifier resolution.
 */
class BoardGamePlayPlayerTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_belongs_to_play(): void
    {
        $play = BoardGamePlay::factory()->create();
        $player = BoardGamePlayPlayer::factory()->create(['board_game_play_id' => $play->id]);

        $this->assertInstanceOf(BoardGamePlay::class, $player->boardGamePlay);
        $this->assertEquals($play->id, $player->boardGamePlay->id);
    }

    public function test_player_belongs_to_user_when_linked(): void
    {
        $user = User::factory()->create();
        $player = BoardGamePlayPlayer::factory()->asUser($user)->create();

        $this->assertInstanceOf(User::class, $player->user);
        $this->assertEquals($user->id, $player->user->id);
    }

    public function test_get_player_identifier_returns_user_name_for_user_player(): void
    {
        $user = User::factory()->create(['name' => 'John Doe']);
        $player = BoardGamePlayPlayer::factory()->asUser($user)->create();

        $this->assertEquals('John Doe', $player->getPlayerIdentifier());
    }

    public function test_get_player_identifier_returns_bgg_username_for_bgg_player(): void
    {
        $player = BoardGamePlayPlayer::factory()->asBggUser('bgguser123')->create();

        $this->assertEquals('bgguser123', $player->getPlayerIdentifier());
    }

    public function test_get_player_identifier_returns_guest_name_for_guest_player(): void
    {
        $player = BoardGamePlayPlayer::factory()->asGuest('Guest Player')->create();

        $this->assertEquals('Guest Player', $player->getPlayerIdentifier());
    }

    public function test_is_user_player_returns_true_for_user_player(): void
    {
        $player = BoardGamePlayPlayer::factory()->asUser()->create();

        $this->assertTrue($player->isUserPlayer());
        $this->assertFalse($player->isBggPlayer());
        $this->assertFalse($player->isGuestPlayer());
    }

    public function test_is_bgg_player_returns_true_for_bgg_player(): void
    {
        $player = BoardGamePlayPlayer::factory()->asBggUser()->create();

        $this->assertFalse($player->isUserPlayer());
        $this->assertTrue($player->isBggPlayer());
        $this->assertFalse($player->isGuestPlayer());
    }

    public function test_is_guest_player_returns_true_for_guest_player(): void
    {
        $player = BoardGamePlayPlayer::factory()->asGuest()->create();

        $this->assertFalse($player->isUserPlayer());
        $this->assertFalse($player->isBggPlayer());
        $this->assertTrue($player->isGuestPlayer());
    }
}

