<?php

declare(strict_types=1);

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PlaceholderController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Public routes
Route::get('/', function () {
    return Inertia::render('Index');
})->name('home');

// Authentication routes
Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});

// Authenticated routes
Route::middleware('auth')->group(function (): void {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/groups', [PlaceholderController::class, 'groups'])->name('groups.index');
    Route::get('/collection', [PlaceholderController::class, 'collection'])->name('collection.index');
    Route::get('/play-log', [PlaceholderController::class, 'playLog'])->name('play-log.index');
    Route::get('/statistics', [PlaceholderController::class, 'statistics'])->name('statistics.index');
    Route::get('/boardgames', [PlaceholderController::class, 'boardgames'])->name('boardgames.index');
    Route::get('/settings', [SettingsController::class, 'index'])->name('settings.index');
    Route::get('/settings/boardgamegeek/status', [SettingsController::class, 'boardGameGeekSyncStatus'])->name('settings.boardgamegeek.status');
    Route::post('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile.update');
    Route::put('/settings/preferences', [SettingsController::class, 'updatePreferences'])->name('settings.preferences.update');
    Route::put('/settings/boardgamegeek', [SettingsController::class, 'updateBoardGameGeek'])->name('settings.boardgamegeek.update');
    Route::post('/settings/boardgamegeek/sync', [SettingsController::class, 'requestManualBoardGameGeekSync'])->name('settings.boardgamegeek.sync');
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
});
