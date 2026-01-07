<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BoardGame;
use App\Models\BoardGamePlay;
use App\Models\BoardGamePlayPlayer;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * BoardGamePlaySeeder for populating the database with board game play test data.
 *
 * This seeder creates sample board game plays for development and testing purposes.
 * It creates both factory-generated plays and specific example plays.
 */
class BoardGamePlaySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding board game plays...');

        // Ensure we have the necessary data
        $users = User::all();
        $groups = Group::all();
        $boardGames = BoardGame::where('is_expansion', false)->get();

        if ($users->isEmpty()) {
            $this->command->warn('No users found. Please run UserSeeder first.');
            return;
        }

        if ($boardGames->isEmpty()) {
            $this->command->warn('No board games found. Please run BoardGameSeeder first.');
            return;
        }

        // Create random plays using the factory
        $this->seedFromFactory($users, $groups, $boardGames);

        // Create specific example plays
        $this->seedExamplePlays($users, $groups, $boardGames);

        $this->command->info('Board game plays seeded successfully');
    }

    /**
     * Seed plays from factory.
     *
     * @param \Illuminate\Database\Eloquent\Collection $users
     * @param \Illuminate\Database\Eloquent\Collection $groups
     * @param \Illuminate\Database\Eloquent\Collection $boardGames
     */
    private function seedFromFactory($users, $groups, $boardGames): void
    {
        $this->command->info('Creating factory-generated plays...');

        // Create 50 random plays
        BoardGamePlay::factory()
            ->count(50)
            ->create()
            ->each(function (BoardGamePlay $play) use ($users, $groups, $boardGames) {
                // Ensure play has a valid board game
                if (!$boardGames->contains('id', $play->board_game_id)) {
                    $play->board_game_id = $boardGames->random()->id;
                    $play->save();
                }

                // Randomly assign to a group (70% chance)
                if ($groups->isNotEmpty() && rand(1, 100) <= 70) {
                    $play->group_id = $groups->random()->id;
                    $play->save();
                }

                // Create 1-6 players for each play
                $playerCount = rand(1, 6);
                $playUsers = $users->random(min($playerCount, $users->count()));

                foreach ($playUsers as $index => $user) {
                    BoardGamePlayPlayer::factory()
                        ->forPlay($play)
                        ->forUser($user)
                        ->create([
                            'position' => $index + 1,
                            'is_winner' => $index === 0 && rand(1, 100) <= 50, // First player has 50% chance of winning
                            'score' => rand(1, 100) <= 60 ? rand(0, 1000) : null, // 60% chance of having a score
                        ]);
                }

                // Occasionally add a guest player
                if (rand(1, 100) <= 30 && $playerCount < 6) {
                    BoardGamePlayPlayer::factory()
                        ->forPlay($play)
                        ->asGuest()
                        ->create([
                            'position' => $playerCount + 1,
                        ]);
                }
            });

        $this->command->info('Factory-generated plays created');
    }

    /**
     * Seed specific example plays with realistic data.
     *
     * @param \Illuminate\Database\Eloquent\Collection $users
     * @param \Illuminate\Database\Eloquent\Collection $groups
     * @param \Illuminate\Database\Eloquent\Collection $boardGames
     */
    private function seedExamplePlays($users, $groups, $boardGames): void
    {
        $this->command->info('Creating example plays...');

        $creatorUser = $users->first();
        $exampleGroup = $groups->first();
        $exampleBoardGame = $boardGames->first();

        if (!$creatorUser || !$exampleBoardGame) {
            $this->command->warn('Skipping example plays - missing required data');
            return;
        }

        // Create a recent play with multiple players
        $recentPlay = BoardGamePlay::factory()->create([
            'board_game_id' => $exampleBoardGame->id,
            'group_id' => $exampleGroup?->id,
            'created_by_user_id' => $creatorUser->id,
            'played_at' => now()->subDays(2),
            'location' => 'Game Store',
            'comment' => 'Great game! Everyone enjoyed it. Looking forward to playing again.',
            'game_length_minutes' => 90,
            'source' => 'website',
        ]);

        // Add players to the recent play
        $playUsers = $users->take(4);
        foreach ($playUsers as $index => $user) {
            BoardGamePlayPlayer::factory()
                ->forPlay($recentPlay)
                ->forUser($user)
                ->create([
                    'position' => $index + 1,
                    'score' => [150, 120, 95, 80][$index] ?? null,
                    'is_winner' => $index === 0,
                    'is_new_player' => $index === 2, // Third player is new
                ]);
        }

        // Create a play from last week
        $lastWeekPlay = BoardGamePlay::factory()->create([
            'board_game_id' => $boardGames->skip(1)->first()?->id ?? $exampleBoardGame->id,
            'group_id' => $exampleGroup?->id,
            'created_by_user_id' => $creatorUser->id,
            'played_at' => now()->subWeek(),
            'location' => 'Home',
            'comment' => 'Quick game before dinner',
            'game_length_minutes' => 45,
            'source' => 'website',
        ]);

        // Add 2 players
        $playUsers = $users->take(2);
        foreach ($playUsers as $index => $user) {
            BoardGamePlayPlayer::factory()
                ->forPlay($lastWeekPlay)
                ->forUser($user)
                ->create([
                    'position' => $index + 1,
                    'is_winner' => $index === 0,
                ]);
        }

        // Create a play from BoardGameGeek (synced)
        if ($boardGames->count() > 2) {
            $bggPlay = BoardGamePlay::factory()->create([
                'board_game_id' => $boardGames->skip(2)->first()->id,
                'group_id' => null, // Not associated with a group
                'created_by_user_id' => $creatorUser->id,
                'played_at' => now()->subMonth(),
                'location' => 'Convention',
                'comment' => 'Played at Gen Con 2024',
                'game_length_minutes' => 120,
                'source' => 'boardgamegeek',
                'bgg_play_id' => '12345678',
                'bgg_synced_at' => now()->subMonth(),
                'bgg_sync_status' => 'synced',
            ]);

            // Add players with BGG usernames and guests
            BoardGamePlayPlayer::factory()
                ->forPlay($bggPlay)
                ->asBggUser('bggplayer1')
                ->create([
                    'position' => 1,
                    'score' => 200,
                    'is_winner' => true,
                ]);

            BoardGamePlayPlayer::factory()
                ->forPlay($bggPlay)
                ->asGuest('Guest Player')
                ->create([
                    'position' => 2,
                    'score' => 150,
                ]);
        }

        // Create a play with expansions
        if ($boardGames->count() > 3) {
            $baseGame = $boardGames->skip(3)->first();
            $expansions = BoardGame::where('is_expansion', true)->take(2)->get();

            if ($baseGame && $expansions->isNotEmpty()) {
                $playWithExpansions = BoardGamePlay::factory()->create([
                    'board_game_id' => $baseGame->id,
                    'group_id' => $exampleGroup?->id,
                    'created_by_user_id' => $creatorUser->id,
                    'played_at' => now()->subDays(5),
                    'location' => 'Friend\'s House',
                    'comment' => 'Played with both expansions - amazing experience!',
                    'game_length_minutes' => 180,
                    'source' => 'website',
                ]);

                // Attach expansions
                $playWithExpansions->expansions()->attach($expansions->pluck('id'));

                // Add players
                $playUsers = $users->take(3);
                foreach ($playUsers as $index => $user) {
                    BoardGamePlayPlayer::factory()
                        ->forPlay($playWithExpansions)
                        ->forUser($user)
                        ->create([
                            'position' => $index + 1,
                            'is_winner' => $index === 1, // Second player wins
                        ]);
                }
            }
        }

        $this->command->info('Example plays created');
    }
}

