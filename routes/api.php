<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\ApiAuthController;
use App\Http\Controllers\Api\V1\BoardGameController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->group(function (): void {
    // Public authentication routes
    Route::post('/auth/login', [ApiAuthController::class, 'login'])->name('api.auth.login');
    Route::post('/auth/register', [ApiAuthController::class, 'register'])->name('api.auth.register');

    // Protected authentication routes
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/auth/logout', [ApiAuthController::class, 'logout'])->name('api.auth.logout');
        Route::get('/auth/me', [ApiAuthController::class, 'me'])->name('api.auth.me');
    });

    // Protected Board Games API routes
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/board-games', [BoardGameController::class, 'index'])->name('api.board-games.index');
        Route::get('/board-games/{id}', [BoardGameController::class, 'show'])->name('api.board-games.show');
    });
});

