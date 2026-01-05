<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\GroupMember>
 */
class GroupMemberFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = GroupMember::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => Group::factory(),
            'user_id' => User::factory(),
            'role' => GroupMember::ROLE_GROUP_MEMBER,
            'joined_at' => now(),
        ];
    }

    /**
     * Indicate that the member is a group admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => GroupMember::ROLE_GROUP_ADMIN,
        ]);
    }

    /**
     * Indicate that the member is a regular group member.
     */
    public function member(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => GroupMember::ROLE_GROUP_MEMBER,
        ]);
    }
}
