<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * UserSeeder for populating the database with user test data.
 *
 * This seeder creates sample users for development and testing purposes.
 * It creates both factory-generated users and specific test users.
 */
class UserSeeder extends Seeder
{
    /**
     * Number of random users to create using the factory.
     */
    private const RANDOM_USER_COUNT = 25;

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Seeding users...');

        // Create the test user first (used by other seeders)
        $this->seedTestUser();

        // Create random users using the factory
        $this->seedFromFactory();

        // Create some specific example users
        $this->seedExampleUsers();

        $this->command->info('Users seeded successfully');
    }

    /**
     * Seed the test user (used for testing and as a default admin).
     */
    private function seedTestUser(): void
    {
        $this->command->info('Creating test user...');

        User::firstOrCreate(
            ['email' => 'test@example.com'],
            [
                'name' => 'Test User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Test user created (email: test@example.com, password: password)');
    }

    /**
     * Seed users from factory.
     */
    private function seedFromFactory(): void
    {
        $this->command->info('Creating factory-generated users...');

        // Create random users using the factory
        User::factory()->count(self::RANDOM_USER_COUNT)->create();

        $this->command->info('Factory-generated users created');
    }

    /**
     * Seed specific example users with realistic data.
     */
    private function seedExampleUsers(): void
    {
        $this->command->info('Creating example users...');

        // Create a group admin user
        User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Group Admin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Create a regular member user
        User::firstOrCreate(
            ['email' => 'member@example.com'],
            [
                'name' => 'Regular Member',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Create a board game enthusiast
        User::firstOrCreate(
            ['email' => 'gamer@example.com'],
            [
                'name' => 'Board Game Enthusiast',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        // Create a casual player
        User::firstOrCreate(
            ['email' => 'casual@example.com'],
            [
                'name' => 'Casual Player',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $this->command->info('Example users created');
        $this->command->info('All example users use password: password');
    }
}

