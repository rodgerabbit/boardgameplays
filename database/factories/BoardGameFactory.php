<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BoardGame;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * BoardGameFactory for generating test board game data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BoardGame>
 */
class BoardGameFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = BoardGame::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $minPlayers = fake()->numberBetween(1, 4);
        $maxPlayers = $minPlayers + fake()->numberBetween(1, 6);

        return [
            'name' => fake()->words(3, true) . ' Game',
            'description' => fake()->optional()->paragraph(),
            'min_players' => $minPlayers,
            'max_players' => $maxPlayers,
            'playing_time_minutes' => fake()->optional()->randomElement([30, 45, 60, 90, 120, 180]),
            'year_published' => fake()->optional()->numberBetween(1950, now()->year),
            'publisher' => fake()->optional()->company(),
            'designer' => fake()->optional()->name(),
            'image_url' => fake()->optional()->imageUrl(),
            'thumbnail_url' => fake()->optional()->imageUrl(200, 200),
            'bgg_id' => fake()->boolean(70) ? fake()->unique()->numerify('#####') : null,
            'bgg_rating' => fake()->optional(0.7)->randomFloat(3, 0, 10),
            'complexity_rating' => fake()->optional(0.7)->randomFloat(3, 0, 5),
            'is_expansion' => fake()->boolean(20),
        ];
    }
}
