<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Group;
use App\Models\GroupMember;
use App\Models\User;
use App\Services\UserSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Unit tests for UserSettingsService.
 *
 * These tests verify the UserSettingsService business logic,
 * including updating settings and managing default groups.
 */
class UserSettingsServiceTest extends TestCase
{
    use RefreshDatabase;

    private UserSettingsService $userSettingsService;

    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->userSettingsService = new UserSettingsService();
    }

    /**
     * Test that updateUserSettings can update default group.
     */
    public function test_update_user_settings_can_update_default_group(): void
    {
        $user = User::factory()->create();
        $group = Group::factory()->create();
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
        ]);

        $updatedUser = $this->userSettingsService->updateUserSettings($user, [
            'default_group_id' => $group->id,
        ]);

        $this->assertEquals($group->id, $updatedUser->default_group_id);
    }

    /**
     * Test that updateUserSettings throws exception for invalid default group.
     */
    public function test_update_user_settings_throws_exception_for_invalid_default_group(): void
    {
        $user = User::factory()->create();
        $group = Group::factory()->create();
        // User is not a member of this group

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('The specified group does not exist or you are not a member of it.');

        $this->userSettingsService->updateUserSettings($user, [
            'default_group_id' => $group->id,
        ]);
    }

    /**
     * Test that updateUserSettings can update theme preference.
     */
    public function test_update_user_settings_can_update_theme_preference(): void
    {
        $user = User::factory()->create();

        $updatedUser = $this->userSettingsService->updateUserSettings($user, [
            'theme_preference' => User::THEME_DARK,
        ]);

        $this->assertEquals(User::THEME_DARK, $updatedUser->theme_preference);
    }

    /**
     * Test that updateUserSettings throws exception for invalid theme preference.
     */
    public function test_update_user_settings_throws_exception_for_invalid_theme_preference(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid theme preference.');

        $this->userSettingsService->updateUserSettings($user, [
            'theme_preference' => 'invalid',
        ]);
    }

    /**
     * Test that updateUserSettings can update play notification delay.
     */
    public function test_update_user_settings_can_update_play_notification_delay(): void
    {
        $user = User::factory()->create();

        $updatedUser = $this->userSettingsService->updateUserSettings($user, [
            'play_notification_delay_hours' => 2,
        ]);

        $this->assertEquals(2, $updatedUser->play_notification_delay_hours);
    }

    /**
     * Test that updateUserSettings throws exception for negative delay.
     */
    public function test_update_user_settings_throws_exception_for_negative_delay(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Play notification delay must be between 0 and');

        $this->userSettingsService->updateUserSettings($user, [
            'play_notification_delay_hours' => -1,
        ]);
    }

    /**
     * Test that updateUserSettings throws exception for delay above maximum.
     */
    public function test_update_user_settings_throws_exception_for_delay_above_maximum(): void
    {
        $user = User::factory()->create();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Play notification delay must be between 0 and');

        $this->userSettingsService->updateUserSettings($user, [
            'play_notification_delay_hours' => 5,
        ]);
    }

    /**
     * Test that setDefaultGroupToFirst sets default group when not set.
     */
    public function test_set_default_group_to_first_sets_default_when_not_set(): void
    {
        $user = User::factory()->create();
        $group = Group::factory()->create();
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
        ]);

        $updatedUser = $this->userSettingsService->setDefaultGroupToFirst($user);

        $this->assertEquals($group->id, $updatedUser->default_group_id);
    }

    /**
     * Test that setDefaultGroupToFirst does not change when default is already set and valid.
     */
    public function test_set_default_group_to_first_does_not_change_when_default_valid(): void
    {
        $user = User::factory()->create();
        $defaultGroup = Group::factory()->create();
        GroupMember::factory()->create([
            'group_id' => $defaultGroup->id,
            'user_id' => $user->id,
        ]);
        $user->update(['default_group_id' => $defaultGroup->id]);

        $updatedUser = $this->userSettingsService->setDefaultGroupToFirst($user);

        $this->assertEquals($defaultGroup->id, $updatedUser->default_group_id);
    }

    /**
     * Test that setDefaultGroupToFirst updates when default is invalid.
     */
    public function test_set_default_group_to_first_updates_when_default_invalid(): void
    {
        $user = User::factory()->create();
        $invalidGroup = Group::factory()->create();
        $validGroup = Group::factory()->create();
        
        $user->update(['default_group_id' => $invalidGroup->id]);
        GroupMember::factory()->create([
            'group_id' => $validGroup->id,
            'user_id' => $user->id,
        ]);

        $updatedUser = $this->userSettingsService->setDefaultGroupToFirst($user);

        $this->assertEquals($validGroup->id, $updatedUser->default_group_id);
    }

    /**
     * Test that getEffectiveDefaultGroupId returns default group when set and valid.
     */
    public function test_get_effective_default_group_id_returns_default_when_set_and_valid(): void
    {
        $user = User::factory()->create();
        $group = Group::factory()->create();
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
        ]);
        $user->update(['default_group_id' => $group->id]);

        $result = $this->userSettingsService->getEffectiveDefaultGroupId($user);

        $this->assertEquals($group->id, $result);
    }

    /**
     * Test that getEffectiveDefaultGroupId returns first group when default not set.
     */
    public function test_get_effective_default_group_id_returns_first_when_default_not_set(): void
    {
        $user = User::factory()->create();
        $group = Group::factory()->create();
        GroupMember::factory()->create([
            'group_id' => $group->id,
            'user_id' => $user->id,
        ]);

        $result = $this->userSettingsService->getEffectiveDefaultGroupId($user);

        $this->assertEquals($group->id, $result);
    }

    /**
     * Test that getEffectiveDefaultGroupId returns null when user has no groups.
     */
    public function test_get_effective_default_group_id_returns_null_when_no_groups(): void
    {
        $user = User::factory()->create();

        $result = $this->userSettingsService->getEffectiveDefaultGroupId($user);

        $this->assertNull($result);
    }
}
