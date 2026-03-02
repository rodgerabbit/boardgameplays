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

    /*
    |--------------------------------------------------------------------------
    | Generic BGG Credentials
    |--------------------------------------------------------------------------
    |
    | Generic credentials for syncing plays to BoardGameGeek.com.
    | These can be used as a fallback when user-specific credentials are not available.
    |
    */
    'generic_username' => env('BOARDGAMEGEEK_GENERIC_USERNAME'),
    'generic_password' => env('BOARDGAMEGEEK_GENERIC_PASSWORD'),

    /*
    |--------------------------------------------------------------------------
    | Play Submission Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Rate limiting for play submission to BGG.
    |
    */
    'play_submission_rate_limit_seconds' => env('BOARDGAMEGEEK_PLAY_SUBMISSION_RATE_LIMIT_SECONDS', 2),

    /*
    |--------------------------------------------------------------------------
    | Collection Sync
    |--------------------------------------------------------------------------
    |
    | How often to sync each user's BGG collection (in days).
    | Collection is fetched via async jobs; this interval is used by the
    | scheduled command to decide which users to queue.
    |
    */
    'collection_sync_interval_days' => (int) env('BOARDGAMEGEEK_COLLECTION_SYNC_INTERVAL_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Collection API URL
    |--------------------------------------------------------------------------
    |
    | Full URL pattern for the BGG collection endpoint. Use {username} as placeholder.
    | stats=1 returns expanded rating/ranking info; version=1 returns version info.
    |
    */
    'collection_api_url' => env(
        'BOARDGAMEGEEK_COLLECTION_API_URL',
        'https://boardgamegeek.com/xmlapi2/collection?username={username}&stats=1&version=1'
    ),

    /*
    | Queue used for board game sync batch jobs (run with priority before import).
    */
    'board_game_sync_queue' => env('BOARDGAMEGEEK_BOARD_GAME_SYNC_QUEUE', 'default'),

    /*
    | Cache TTL in minutes for pending collection/plays data between phases.
    */
    'pending_import_cache_ttl_minutes' => (int) env('BOARDGAMEGEEK_PENDING_IMPORT_CACHE_TTL_MINUTES', 60),

    /*
    |--------------------------------------------------------------------------
    | Plays Sync: Full History Date Range
    |--------------------------------------------------------------------------
    |
    | When syncing all plays (adding a BGG user or manual sync from Settings),
    | this is the earliest date used for the BGG plays API (mindate). BGG
    | launched in 2000; use an earlier date to include all possible plays.
    |
    */
    'plays_sync_earliest_date' => env('BOARDGAMEGEEK_PLAYS_SYNC_EARLIEST_DATE', '2000-01-01'),

    /*
    |--------------------------------------------------------------------------
    | Plays Sync Job Timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | Each plays sync job fetches one BGG page. Timeout should allow for one
    | HTTP request (BGG can be slow) plus payload building and cache merge.
    | Worker must be started with at least this timeout (e.g. --timeout=120).
    |
    */
    'plays_sync_job_timeout_seconds' => (int) env('BOARDGAMEGEEK_PLAYS_SYNC_JOB_TIMEOUT_SECONDS', 120),

    /*
    |--------------------------------------------------------------------------
    | Collection Sync Job Timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | Initial job only fetches the collection (one request). Thing-ID resolution
    | runs in SyncCollectionThingIdsChunkJob. Worker should use --timeout >= this.
    |
    */
    'collection_sync_job_timeout_seconds' => (int) env('BOARDGAMEGEEK_COLLECTION_SYNC_JOB_TIMEOUT_SECONDS', 180),

    /*
    |--------------------------------------------------------------------------
    | Collection Thing-ID Chunk Job Timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | Each chunk job does one rate-limited request to resolve thing IDs to base
    | game IDs. Worker should use --timeout >= this.
    |
    */
    'collection_chunk_job_timeout_seconds' => (int) env('BOARDGAMEGEEK_COLLECTION_CHUNK_JOB_TIMEOUT_SECONDS', 120),
];


