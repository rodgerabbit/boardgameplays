<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\AuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Controller for handling web-based authentication (session-based).
 *
 * This controller handles login, registration, and logout for the web interface
 * using Laravel's session-based authentication.
 */
class AuthController extends Controller
{
    /**
     * Create a new authentication controller instance.
     */
    public function __construct(
        private readonly AuthenticationService $authenticationService
    ) {
    }

    /**
     * Show the login form.
     */
    public function showLoginForm(): Response
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Show the registration form.
     */
    public function showRegisterForm(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle a login request.
     */
    public function login(LoginRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        try {
            $user = $this->authenticationService->authenticateUser(
                $validated['email'],
                $validated['password'],
                $validated['remember'] ?? false
            );

            Auth::login($user, $validated['remember'] ?? false);

            $request->session()->regenerate();

            return redirect()->intended('/');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors([
                'email' => 'The provided credentials are incorrect.',
            ])->onlyInput('email');
        }
    }

    /**
     * Handle a registration request.
     */
    public function register(RegisterRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $user = $this->authenticationService->registerUser($validated);

        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->intended('/');
    }

    /**
     * Handle a logout request.
     */
    public function logout(): RedirectResponse
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/');
    }
}
