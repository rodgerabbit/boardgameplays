<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Group Rate Limiting
    |--------------------------------------------------------------------------
    |
    | These settings control rate limiting for group creation and updates.
    | Values are in seconds.
    |
    */
    'rate_limits' => [
        'create_seconds' => env('GROUP_CREATE_RATE_LIMIT_SECONDS', 300), // 5 minutes
        'update_seconds' => env('GROUP_UPDATE_RATE_LIMIT_SECONDS', 10), // 10 seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Hard Delete Retention Period
    |--------------------------------------------------------------------------
    |
    | Groups soft-deleted for longer than this period (in months) will be
    | permanently deleted by the scheduled command.
    |
    */
    'hard_delete_retention_months' => env('GROUP_HARD_DELETE_RETENTION_MONTHS', 12),

    /*
    |--------------------------------------------------------------------------
    | Group Member Roles
    |--------------------------------------------------------------------------
    |
    | Constants for group member roles.
    |
    */
    'member_roles' => [
        'group_admin' => 'group_admin',
        'group_member' => 'group_member',
    ],
];

