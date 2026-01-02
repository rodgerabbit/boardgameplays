<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * GroupSeeder for populating the database with group test data.
 *
 * This seeder creates sample groups for development and testing purposes.
 * It creates both factory-generated groups and specific example groups.
 */
class GroupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding groups...');

        // Create random groups using the factory
        $this->seedFromFactory();

        // Create specific example groups
        $this->seedExampleGroups();

        $this->command->info('Groups seeded successfully');
    }

    /**
     * Seed groups from factory.
     */
    private function seedFromFactory(): void
    {
        $this->command->info('Creating factory-generated groups...');

        // Create 20 random groups using the factory
        Group::factory()->count(20)->create();

        $this->command->info('Factory-generated groups created');
    }

    /**
     * Seed specific example groups with realistic data.
     */
    private function seedExampleGroups(): void
    {
        $this->command->info('Creating example groups...');

        // Get or create some users for group members
        $users = User::all();
        $testUser = User::firstWhere('email', 'test@example.com');

        // Create a local board game meetup group
        $localMeetupGroup = Group::factory()->create([
            'friendly_name' => 'Local Board Game Meetup',
            'description' => 'A friendly group of board game enthusiasts who meet weekly to play modern board games. All skill levels welcome!',
            'group_location' => 'Seattle, WA',
            'website_link' => 'https://example.com/local-meetup',
            'discord_link' => 'https://discord.gg/local-meetup',
            'slack_link' => null,
        ]);

        // Create a competitive gaming group
        $competitiveGroup = Group::factory()->create([
            'friendly_name' => 'Competitive Strategy Games League',
            'description' => 'For serious gamers who enjoy competitive play and tournaments. We focus on strategy games and track detailed statistics.',
            'group_location' => 'Portland, OR',
            'website_link' => 'https://example.com/competitive-league',
            'discord_link' => 'https://discord.gg/competitive-league',
            'slack_link' => 'https://competitive-league.slack.com',
        ]);

        // Create a casual family gaming group
        $familyGroup = Group::factory()->create([
            'friendly_name' => 'Family Game Night Group',
            'description' => 'A welcoming community for families who love playing board games together. We focus on family-friendly games suitable for all ages.',
            'group_location' => 'Vancouver, BC',
            'website_link' => null,
            'discord_link' => null,
            'slack_link' => null,
        ]);

        // Create a Eurogames-focused group
        $eurogamesGroup = Group::factory()->create([
            'friendly_name' => 'Eurogames Enthusiasts',
            'description' => 'Dedicated to European-style board games. We love worker placement, resource management, and strategic gameplay.',
            'group_location' => 'San Francisco, CA',
            'website_link' => 'https://example.com/eurogames',
            'discord_link' => 'https://discord.gg/eurogames',
            'slack_link' => null,
        ]);

        // Create a cooperative games group
        $cooperativeGroup = Group::factory()->create([
            'friendly_name' => 'Cooperative Games Collective',
            'description' => 'We specialize in cooperative board games where everyone works together. Perfect for those who prefer collaboration over competition.',
            'group_location' => 'Austin, TX',
            'website_link' => null,
            'discord_link' => 'https://discord.gg/cooperative-games',
            'slack_link' => null,
        ]);

        // Add members to groups if users exist
        if ($users->isNotEmpty()) {
            $this->addMembersToGroups([
                $localMeetupGroup,
                $competitiveGroup,
                $familyGroup,
                $eurogamesGroup,
                $cooperativeGroup,
            ], $users, $testUser);
        }

        $this->command->info('Example groups created');
    }

    /**
     * Add members to groups.
     *
     * @param array<Group> $groups The groups to add members to
     * @param \Illuminate\Database\Eloquent\Collection<int, User> $users Available users
     * @param User|null $testUser The test user (if available)
     */
    private function addMembersToGroups(array $groups, $users, ?User $testUser): void
    {
        $this->command->info('Adding members to groups...');

        foreach ($groups as $group) {
            // Add test user as admin if available
            if ($testUser !== null) {
                GroupMember::create([
                    'group_id' => $group->id,
                    'user_id' => $testUser->id,
                    'role' => GroupMember::ROLE_GROUP_ADMIN,
                    'joined_at' => now()->subMonths(6),
                ]);
            }

            // Add 2-5 random members to each group
            $availableUsers = $users->where('id', '!=', $testUser?->id);

            if ($availableUsers->isNotEmpty()) {
                $memberCount = fake()->numberBetween(2, min(5, $availableUsers->count()));
                $selectedUsers = $availableUsers->random($memberCount);

                foreach ($selectedUsers as $user) {
                    GroupMember::create([
                        'group_id' => $group->id,
                        'user_id' => $user->id,
                        'role' => GroupMember::ROLE_GROUP_MEMBER,
                        'joined_at' => now()->subMonths(fake()->numberBetween(1, 12)),
                    ]);
                }
            }
        }

        $this->command->info('Members added to groups');
    }
}

