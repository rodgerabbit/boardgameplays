<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Group;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Group>
 */
class GroupFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Group::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'friendly_name' => fake()->company() . ' Gaming Group',
            'description' => fake()->optional()->paragraph(),
            'group_location' => fake()->optional()->city(),
            'website_link' => fake()->optional()->url(),
            'discord_link' => fake()->optional()->url(),
            'slack_link' => fake()->optional()->url(),
        ];
    }
}
