<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller for the settings page and profile updates.
 */
class SettingsController extends Controller
{
    /**
     * Display the settings page with Profile, BoardGameGeek, and Notifications tabs.
     */
    public function index(): Response
    {
        $user = Auth::user();
        $user->loadMissing([]);

        return Inertia::render('Settings', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'profile_picture_url' => $user->profile_picture_url,
                'biography' => $user->biography,
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
}
