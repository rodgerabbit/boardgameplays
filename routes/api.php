<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AdminGroupController;
use App\Http\Controllers\Api\V1\ApiAuthController;
use App\Http\Controllers\Api\V1\BoardGameController;
use App\Http\Controllers\Api\V1\GroupAuditLogController;
use App\Http\Controllers\Api\V1\GroupController;
use App\Http\Middleware\ThrottleGroupCreation;
use App\Http\Middleware\ThrottleGroupUpdate;
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

    // Protected Groups API routes
    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('/groups', [GroupController::class, 'index'])->name('api.groups.index');
        Route::post('/groups', [GroupController::class, 'store'])
            ->middleware(ThrottleGroupCreation::class)
            ->name('api.groups.store');
        Route::get('/groups/{id}', [GroupController::class, 'show'])->name('api.groups.show');
        Route::match(['put', 'patch'], '/groups/{id}', [GroupController::class, 'update'])
            ->middleware(ThrottleGroupUpdate::class)
            ->name('api.groups.update');
        Route::delete('/groups/{id}', [GroupController::class, 'destroy'])->name('api.groups.destroy');
        Route::get('/groups/{id}/audit-logs', [GroupAuditLogController::class, 'index'])->name('api.groups.audit-logs.index');
    });

    // Protected Admin Groups API routes (system admin only)
    Route::middleware('auth:sanctum')->prefix('admin')->group(function (): void {
        Route::post('/groups/{id}/restore', [AdminGroupController::class, 'restore'])->name('api.admin.groups.restore');
        Route::get('/groups/deleted', [AdminGroupController::class, 'indexDeleted'])->name('api.admin.groups.deleted');
    });
});

