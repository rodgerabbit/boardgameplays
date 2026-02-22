<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncPlayDeduplicationCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMockingConsoleOutput();
    }

    public function test_command_succeeds_with_no_options(): void
    {
        $exitCode = $this->artisan('plays:sync-deduplication');

        $this->assertEquals(0, $exitCode);
    }

    public function test_command_succeeds_with_group_option(): void
    {
        $exitCode = $this->artisan('plays:sync-deduplication', ['--group' => '1']);

        $this->assertEquals(0, $exitCode);
    }

    public function test_command_succeeds_with_date_option(): void
    {
        $exitCode = $this->artisan('plays:sync-deduplication', ['--date' => '2025-01-15']);

        $this->assertEquals(0, $exitCode);
    }

    public function test_command_succeeds_with_from_and_to_options(): void
    {
        $exitCode = $this->artisan('plays:sync-deduplication', [
            '--from' => '2025-01-01',
            '--to' => '2025-01-31',
        ]);

        $this->assertEquals(0, $exitCode);
    }

    public function test_command_succeeds_with_group_and_date(): void
    {
        $exitCode = $this->artisan('plays:sync-deduplication', [
            '--group' => '1',
            '--date' => '2025-01-15',
        ]);

        $this->assertEquals(0, $exitCode);
    }

    public function test_command_fails_when_date_and_from_to_used_together(): void
    {
        $exitCode = $this->artisan('plays:sync-deduplication', [
            '--date' => '2025-01-15',
            '--from' => '2025-01-01',
            '--to' => '2025-01-31',
        ]);

        $this->assertNotEquals(0, $exitCode);
    }

    public function test_command_fails_when_only_from_given(): void
    {
        $exitCode = $this->artisan('plays:sync-deduplication', ['--from' => '2025-01-01']);

        $this->assertNotEquals(0, $exitCode);
    }

    public function test_command_fails_when_only_to_given(): void
    {
        $exitCode = $this->artisan('plays:sync-deduplication', ['--to' => '2025-01-31']);

        $this->assertNotEquals(0, $exitCode);
    }

    public function test_command_fails_when_from_after_to(): void
    {
        $exitCode = $this->artisan('plays:sync-deduplication', [
            '--from' => '2025-01-31',
            '--to' => '2025-01-01',
        ]);

        $this->assertNotEquals(0, $exitCode);
    }

    public function test_command_fails_with_invalid_date(): void
    {
        $exitCode = $this->artisan('plays:sync-deduplication', ['--date' => 'not-a-date']);

        $this->assertNotEquals(0, $exitCode);
    }
}
