<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\BoardGamePlay;
use App\Models\BoardGamePlayPlayer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * BoardGamePlayPlayerFactory for generating test board game play player data.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\BoardGamePlayPlayer>
 */
class BoardGamePlayPlayerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = BoardGamePlayPlayer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $identifierType = fake()->randomElement(['user', 'bgg_username', 'guest']);

        $data = [
            'board_game_play_id' => BoardGamePlay::factory(),
            'score' => fake()->optional(0.6)->numberBetween(0, 1000),
            'is_winner' => fake()->boolean(30),
            'is_new_player' => fake()->boolean(20),
            'position' => fake()->optional(0.5)->numberBetween(1, 10),
        ];

        // Set identifier based on type
        if ($identifierType === 'user') {
            $data['user_id'] = User::factory();
            $data['board_game_geek_username'] = null;
            $data['guest_name'] = null;
        } elseif ($identifierType === 'bgg_username') {
            $data['user_id'] = null;
            $data['board_game_geek_username'] = fake()->unique()->userName();
            $data['guest_name'] = null;
        } else {
            $data['user_id'] = null;
            $data['board_game_geek_username'] = null;
            $data['guest_name'] = fake()->name();
        }

        return $data;
    }

    /**
     * Set the play this player belongs to.
     *
     * @param BoardGamePlay $play
     * @return static
     */
    public function forPlay(BoardGamePlay $play): static
    {
        return $this->state(fn (array $attributes) => [
            'board_game_play_id' => $play->id,
        ]);
    }

    /**
     * Set the user for this player.
     *
     * @param User $user
     * @return static
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);
    }

    /**
     * Indicate that the player is a user.
     *
     * @param User|null $user
     * @return static
     */
    public function asUser(?User $user = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user?->id ?? User::factory(),
            'board_game_geek_username' => null,
            'guest_name' => null,
        ]);
    }

    /**
     * Indicate that the player is identified by BGG username.
     *
     * @param string|null $username
     * @return static
     */
    public function asBggUser(?string $username = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'board_game_geek_username' => $username ?? fake()->unique()->userName(),
            'guest_name' => null,
        ]);
    }

    /**
     * Indicate that the player is a guest.
     *
     * @param string|null $name
     * @return static
     */
    public function asGuest(?string $name = null): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => null,
            'board_game_geek_username' => null,
            'guest_name' => $name ?? fake()->name(),
        ]);
    }

    /**
     * Indicate that the player is a winner.
     *
     * @return static
     */
    public function winner(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_winner' => true,
        ]);
    }

    /**
     * Indicate that the player is new.
     *
     * @return static
     */
    public function newPlayer(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_new_player' => true,
        ]);
    }
}

