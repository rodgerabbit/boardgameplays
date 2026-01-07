<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BoardGame;
use App\Models\BoardGamePlay;
use App\Models\Group;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * BoardGamePlayFactory for generating test board game play data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BoardGamePlay>
 */
class BoardGamePlayFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = BoardGamePlay::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'board_game_id' => BoardGame::factory(),
            'group_id' => null,
            'created_by_user_id' => User::factory(),
            'played_at' => fake()->dateTimeBetween('-1 year', 'now'),
            'location' => fake()->randomElement(['Home', 'Game Store', 'Convention', 'Friend\'s House', 'Online']),
            'comment' => fake()->optional(0.6)->paragraph(),
            'game_length_minutes' => fake()->optional(0.7)->randomElement([30, 45, 60, 90, 120, 180]),
            'source' => fake()->randomElement(['website', 'boardgamegeek']),
            'bgg_play_id' => fake()->optional(0.3) ? fake()->unique()->numerify('########') : null,
            'bgg_synced_at' => null,
            'bgg_sync_status' => null,
            'bgg_sync_error_message' => null,
            'bgg_synced_to_at' => null,
            'bgg_sync_to_status' => null,
            'bgg_sync_to_error_message' => null,
            'sync_to_bgg' => false,
        ];
    }

    /**
     * Indicate that the play is from BoardGameGeek.
     *
     * @return static
     */
    public function fromBoardGameGeek(): static
    {
        return $this->state(fn (array $attributes) => [
            'source' => 'boardgamegeek',
            'bgg_play_id' => fake()->unique()->numerify('########'),
            'bgg_synced_at' => now(),
            'bgg_sync_status' => 'synced',
        ]);
    }

    /**
     * Indicate that the play should sync to BoardGameGeek.
     *
     * @return static
     */
    public function syncToBgg(): static
    {
        return $this->state(fn (array $attributes) => [
            'sync_to_bgg' => true,
            'bgg_sync_to_status' => 'pending',
        ]);
    }

    /**
     * Set the play to belong to a group.
     *
     * @param Group $group
     * @return static
     */
    public function forGroup(Group $group): static
    {
        return $this->state(fn (array $attributes) => [
            'group_id' => $group->id,
        ]);
    }

    /**
     * Set the play to be created by a specific user.
     *
     * @param User $user
     * @return static
     */
    public function createdBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by_user_id' => $user->id,
        ]);
    }
}

