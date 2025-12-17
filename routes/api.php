<?php

declare(strict_types=1);

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
    // Board Games API (read-only)
    Route::get('/board-games', [\App\Http\Controllers\Api\V1\BoardGameController::class, 'index']);
    Route::get('/board-games/{id}', [\App\Http\Controllers\Api\V1\BoardGameController::class, 'show']);
});

