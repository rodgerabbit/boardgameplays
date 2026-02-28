<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\BoardGame;
use App\Models\BoardGamePlay;
use App\Models\User;
use App\Services\BoardGameGeekPlaySubmissionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Tests\TestCase;

/**
 * Unit tests for BoardGameGeekPlaySubmissionService credential resolution.
 */
class BoardGameGeekPlaySubmissionServiceTest extends TestCase
{
    use RefreshDatabase;

    private BoardGameGeekPlaySubmissionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new BoardGameGeekPlaySubmissionService();
    }

    public function test_get_bgg_credentials_returns_generic_when_user_prefers_generic(): void
    {
        Config::set('boardgamegeek.generic_username', 'genericuser');
        Config::set('boardgamegeek.generic_password', 'genericpass');

        $user = User::factory()->create([
            'board_game_geek_username' => 'mybgg',
            'use_generic_user_for_bgg_plays' => true,
            'sync_plays_to_board_game_geek' => true,
        ]);
        $play = $this->createPlayForUser($user);

        $credentials = $this->service->getBggCredentialsForPlay($play);

        $this->assertSame('genericuser', $credentials['username']);
        $this->assertSame('genericpass', $credentials['password']);
    }

    public function test_get_bgg_credentials_returns_user_credentials_when_not_using_generic(): void
    {
        Config::set('boardgamegeek.generic_username', 'genericuser');
        Config::set('boardgamegeek.generic_password', 'genericpass');

        $user = User::factory()->create([
            'board_game_geek_username' => 'mybgg',
            'board_game_geek_password_encrypted' => Crypt::encryptString('userbggpass'),
            'use_generic_user_for_bgg_plays' => false,
            'sync_plays_to_board_game_geek' => true,
        ]);
        $play = $this->createPlayForUser($user);

        $credentials = $this->service->getBggCredentialsForPlay($play);

        $this->assertSame('mybgg', $credentials['username']);
        $this->assertSame('userbggpass', $credentials['password']);
    }

    public function test_get_bgg_credentials_throws_when_no_credentials_available(): void
    {
        Config::set('boardgamegeek.generic_username', null);
        Config::set('boardgamegeek.generic_password', null);

        $user = User::factory()->create([
            'board_game_geek_username' => 'mybgg',
            'board_game_geek_password_encrypted' => null,
            'use_generic_user_for_bgg_plays' => true,
            'sync_plays_to_board_game_geek' => true,
        ]);
        $play = $this->createPlayForUser($user);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No BGG credentials available');

        $this->service->getBggCredentialsForPlay($play);
    }

    public function test_get_bgg_credentials_uses_provided_credentials_when_given(): void
    {
        $user = User::factory()->create();
        $play = $this->createPlayForUser($user);

        $credentials = $this->service->getBggCredentialsForPlay(
            $play,
            'provideduser',
            'providedpass'
        );

        $this->assertSame('provideduser', $credentials['username']);
        $this->assertSame('providedpass', $credentials['password']);
    }

    private function createPlayForUser(User $user): BoardGamePlay
    {
        $boardGame = BoardGame::factory()->create();

        return BoardGamePlay::factory()->create([
            'board_game_id' => $boardGame->id,
            'created_by_user_id' => $user->id,
        ]);
    }
}
