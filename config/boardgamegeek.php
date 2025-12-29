<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | BoardGameGeek API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the BoardGameGeek XML API integration.
    | This service syncs board game data from BoardGameGeek.com.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | API Base URL
    |--------------------------------------------------------------------------
    |
    | The base URL for the BoardGameGeek XML API.
    |
    */
    'api_base_url' => env('BOARDGAMEGEEK_API_BASE_URL', 'https://boardgamegeek.com/xmlapi2'),

    /*
    |--------------------------------------------------------------------------
    | API Token
    |--------------------------------------------------------------------------
    |
    | The authorization token for BoardGameGeek API requests.
    | This token should be stored in the .env file as BOARDGAMEGEEK_API_TOKEN.
    |
    */
    'api_token' => env('BOARDGAMEGEEK_API_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configuration for rate limiting API requests to respect BGG's limits.
    |
    */
    'rate_limiting' => [
        'minimum_seconds_between_requests' => env('BOARDGAMEGEEK_MIN_SECONDS_BETWEEN_REQUESTS', 2),
        'max_ids_per_request' => env('BOARDGAMEGEEK_MAX_IDS_PER_REQUEST', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for retrying failed API requests.
    |
    */
    'retry' => [
        'max_attempts' => env('BOARDGAMEGEEK_MAX_RETRY_ATTEMPTS', 5),
        'retry_after_202_seconds' => env('BOARDGAMEGEEK_RETRY_AFTER_202_SECONDS', 3),
        'exponential_backoff_max_seconds' => env('BOARDGAMEGEEK_EXPONENTIAL_BACKOFF_MAX_SECONDS', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | Concurrency Control
    |--------------------------------------------------------------------------
    |
    | Only one request workload can run simultaneously to respect rate limits.
    |
    */
    'concurrency' => [
        'max_concurrent_requests' => env('BOARDGAMEGEEK_MAX_CONCURRENT_REQUESTS', 1),
    ],
];


