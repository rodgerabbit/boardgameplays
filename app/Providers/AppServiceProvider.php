<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\BoardGameGeekApiClient;
use App\Services\BoardGameGeekSyncService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register BoardGameGeekApiClient as a singleton
        $this->app->singleton(BoardGameGeekApiClient::class, function ($app) {
            return new BoardGameGeekApiClient(
                apiBaseUrl: config('boardgamegeek.api_base_url', 'https://boardgamegeek.com/xmlapi2'),
                apiToken: config('boardgamegeek.api_token'),
                minimumSecondsBetweenRequests: (int) config('boardgamegeek.rate_limiting.minimum_seconds_between_requests', 2),
                maxIdsPerRequest: (int) config('boardgamegeek.rate_limiting.max_ids_per_request', 20),
                maxRetryAttempts: (int) config('boardgamegeek.retry.max_attempts', 5),
                retryAfter202Seconds: (int) config('boardgamegeek.retry.retry_after_202_seconds', 3),
                exponentialBackoffMaxSeconds: (int) config('boardgamegeek.retry.exponential_backoff_max_seconds', 60),
            );
        });

        // Register BoardGameGeekSyncService
        $this->app->bind(BoardGameGeekSyncService::class, function ($app) {
            return new BoardGameGeekSyncService(
                apiClient: $app->make(BoardGameGeekApiClient::class),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
