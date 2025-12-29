<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\BoardGame;
use Illuminate\Database\Seeder;

/**
 * BoardGameSeeder for populating the database with board game test data.
 *
 * This seeder creates sample board games for development and testing purposes.
 */
class BoardGameSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create 50 random board games using the factory
        BoardGame::factory()->count(50)->create();

        // Create some well-known board games with specific attributes
        BoardGame::factory()->create([
            'name' => 'Catan',
            'description' => 'Players try to be the dominant force on the island of Catan by building settlements, cities, and roads.',
            'min_players' => 3,
            'max_players' => 4,
            'playing_time_minutes' => 90,
            'year_published' => 1995,
            'publisher' => 'Catan Studio',
            'designer' => 'Klaus Teuber',
            'bgg_id' => '13',
        ]);

        BoardGame::factory()->create([
            'name' => 'Ticket to Ride',
            'description' => 'Build your train routes across North America in this award-winning game.',
            'min_players' => 2,
            'max_players' => 5,
            'playing_time_minutes' => 60,
            'year_published' => 2004,
            'publisher' => 'Days of Wonder',
            'designer' => 'Alan R. Moon',
            'bgg_id' => '9209',
        ]);

        BoardGame::factory()->create([
            'name' => 'Wingspan',
            'description' => 'Attract a beautiful and diverse collection of birds to your wildlife preserve.',
            'min_players' => 1,
            'max_players' => 5,
            'playing_time_minutes' => 70,
            'year_published' => 2019,
            'publisher' => 'Stonemaier Games',
            'designer' => 'Elizabeth Hargrave',
            'bgg_id' => '266524',
        ]);
    }
}




