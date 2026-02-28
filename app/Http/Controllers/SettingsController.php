<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBoardGameGeekSettingsRequest;
use App\Http\Requests\UpdatePreferencesRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Jobs\SyncBoardGamePlaysFromBoardGameGeekJob;
use App\Jobs\SyncUserCollectionFromBoardGameGeekJob;
use App\Models\BggCollectionSync;
use App\Models\BggPlaysSync;
use App\Models\User;
use App\Services\UserSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller for the settings page and profile updates.
 */
class SettingsController extends Controller
{
    /**
     * Minimum hours between manual BGG sync requests.
     */
    private const BGG_MANUAL_SYNC_COOLDOWN_HOURS = 24;

    /**
     * Display the settings page with Profile, BoardGameGeek, and Notifications tabs.
     */
    public function index(Request $request): Response
    {
        $user = Auth::user();
        $user->loadMissing([]);

        $lastCollectionSync = $user->bggCollectionSyncs()
            ->orderByDesc('synced_at')
            ->first();
        $lastPlaysSync = $user->bggPlaysSyncs()
            ->orderByDesc('synced_at')
            ->first();

        $bggManualSyncRequestedAt = $user->bgg_manual_sync_requested_at;
        $manualSyncAllowed = $bggManualSyncRequestedAt === null
            || $bggManualSyncRequestedAt->diffInHours(now(), false) >= self::BGG_MANUAL_SYNC_COOLDOWN_HOURS;

        return Inertia::render('Settings', [
            'activeTab' => $request->session()->get('activeTab', 'profile'),
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'profile_picture_url' => $user->profile_picture_url,
                'biography' => $user->biography,
                'theme_preference' => $user->theme_preference ?? User::THEME_SYSTEM,
                'is_profile_public' => $user->is_profile_public ?? false,
                'board_game_geek_username' => $user->board_game_geek_username,
                'sync_plays_to_board_game_geek' => (bool) $user->sync_plays_to_board_game_geek,
                'use_generic_user_for_bgg_plays' => $user->use_generic_user_for_bgg_plays ?? true,
            ],
            'boardGameGeek' => [
                'last_collection_sync' => $lastCollectionSync ? [
                    'synced_at' => $lastCollectionSync->synced_at->toIso8601String(),
                    'status' => $lastCollectionSync->status,
                    'error_message' => $lastCollectionSync->error_message,
                ] : null,
                'last_plays_sync' => $lastPlaysSync ? [
                    'synced_at' => $lastPlaysSync->synced_at->toIso8601String(),
                    'status' => $lastPlaysSync->status,
                    'error_message' => $lastPlaysSync->error_message,
                ] : null,
                'bgg_manual_sync_requested_at' => $bggManualSyncRequestedAt?->toIso8601String(),
                'manual_sync_allowed' => $manualSyncAllowed,
            ],
        ]);
    }

    /**
     * Update the authenticated user's profile (name, biography, optional profile picture).
     */
    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        $user->name = $validated['name'];
        $user->biography = $validated['biography'] ?? null;

        if ($request->hasFile('profile_picture')) {
            $file = $request->file('profile_picture');
            $directory = 'profiles/' . $user->id;

            // Remove previous profile picture if it exists
            if ($user->profile_picture_path) {
                Storage::disk('public')->delete($user->profile_picture_path);
            }

            $path = $file->store($directory, 'public');
            $user->profile_picture_path = $path;
        }

        $user->save();

        return redirect()->route('settings.index')->with('success', 'Profile updated successfully.');
    }

    /**
     * Update the authenticated user's preferences (e.g. theme).
     */
    public function updatePreferences(UpdatePreferencesRequest $request, UserSettingsService $userSettingsService): RedirectResponse
    {
        $user = Auth::user();
        $userSettingsService->updateUserSettings($user, $request->validated());

        return redirect()->route('settings.index')->with('success', 'Preferences updated.');
    }

    /**
     * Update the authenticated user's BoardGameGeek settings.
     */
    public function updateBoardGameGeek(UpdateBoardGameGeekSettingsRequest $request): RedirectResponse
    {
        $user = Auth::user();
        $validated = $request->validated();

        $previousUsername = $user->board_game_geek_username;
        $newUsername = $validated['board_game_geek_username'] ?? null;

        $user->board_game_geek_username = $newUsername;
        $user->sync_plays_to_board_game_geek = $validated['sync_plays_to_board_game_geek'] ?? $user->sync_plays_to_board_game_geek;
        $user->use_generic_user_for_bgg_plays = $validated['use_generic_user_for_bgg_plays'] ?? $user->use_generic_user_for_bgg_plays;

        if (array_key_exists('board_game_geek_password', $validated) && is_string($validated['board_game_geek_password']) && $validated['board_game_geek_password'] !== '') {
            $user->board_game_geek_password_encrypted = Crypt::encryptString($validated['board_game_geek_password']);
        }

        $user->save();

        // Dispatch sync jobs when the user now has a BGG username and either didn't have one before or it changed (use persisted value to avoid request/validation quirks)
        $currentUsername = $user->board_game_geek_username;
        $nowHasUsername = $currentUsername !== null && trim($currentUsername) !== '';
        $hadUsernameBefore = $previousUsername !== null && trim((string) $previousUsername) !== '';
        $usernameSetOrChanged = $nowHasUsername && (! $hadUsernameBefore || $previousUsername !== $currentUsername);

        if ($usernameSetOrChanged) {
            SyncUserCollectionFromBoardGameGeekJob::dispatch($user->id)
                ->delay(now()->addSeconds(2));
            SyncBoardGamePlaysFromBoardGameGeekJob::dispatch($user->id)
                ->delay(now()->addSeconds(4));
        }

        return redirect()->route('settings.index')
            ->with('success', 'BoardGameGeek settings saved.')
            ->with('activeTab', 'boardgamegeek');
    }

    /**
     * Request a manual sync of collection and plays from BoardGameGeek (once per 24 hours).
     */
    public function requestManualBoardGameGeekSync(): RedirectResponse
    {
        $user = Auth::user();
        $requestedAt = $user->bgg_manual_sync_requested_at;

        if ($user->board_game_geek_username === null || $user->board_game_geek_username === '') {
            return redirect()->route('settings.index')
                ->with('error', 'Set your BoardGameGeek username first.')
                ->with('activeTab', 'boardgamegeek');
        }

        $allowed = $requestedAt === null
            || $requestedAt->diffInHours(now(), false) >= self::BGG_MANUAL_SYNC_COOLDOWN_HOURS;

        if (! $allowed) {
            return redirect()->route('settings.index')
                ->with('error', 'You can request a manual sync once every 24 hours.')
                ->with('activeTab', 'boardgamegeek');
        }

        $user->bgg_manual_sync_requested_at = now();
        $user->save();

        SyncUserCollectionFromBoardGameGeekJob::dispatch($user->id)
            ->delay(now()->addSeconds(2));
        SyncBoardGamePlaysFromBoardGameGeekJob::dispatch($user->id, null, null, true)
            ->delay(now()->addSeconds(4));

        return redirect()->route('settings.index')
            ->with('success', 'Sync requested. Collection and plays will be updated shortly.')
            ->with('activeTab', 'boardgamegeek');
    }
}
