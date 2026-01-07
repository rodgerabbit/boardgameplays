<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Models\BoardGame;
use App\Models\BoardGamePlay;
use App\Models\BoardGamePlayPlayer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Integration tests for board game play database constraints.
 *
 * These tests verify database-level constraints and cascades.
 */
class BoardGamePlayDatabaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_deleting_board_game_cascades_to_plays(): void
    {
        $boardGame = BoardGame::factory()->create(['is_expansion' => false]);
        $play = BoardGamePlay::factory()->create(['board_game_id' => $boardGame->id]);

        $boardGame->delete();

        $this->assertDatabaseMissing('board_game_plays', ['id' => $play->id]);
    }

    public function test_deleting_play_cascades_to_players(): void
    {
        $play = BoardGamePlay::factory()->create();
        $player = BoardGamePlayPlayer::factory()->create(['board_game_play_id' => $play->id]);

        $play->delete();

        $this->assertDatabaseMissing('board_game_play_players', ['id' => $player->id]);
    }

    public function test_deleting_group_sets_group_id_to_null(): void
    {
        $user = User::factory()->create();
        $group = \App\Models\Group::factory()->create();
        $play = BoardGamePlay::factory()->create(['group_id' => $group->id]);

        // Use forceDelete to actually delete the group (not soft delete)
        // This will trigger the foreign key constraint with onDelete('set null')
        $group->forceDelete();

        $play->refresh();
        $this->assertNull($play->group_id);
    }

    public function test_bgg_play_id_is_unique(): void
    {
        $bggPlayId = '12345678';
        BoardGamePlay::factory()->create(['bgg_play_id' => $bggPlayId]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        BoardGamePlay::factory()->create(['bgg_play_id' => $bggPlayId]);
    }
}

