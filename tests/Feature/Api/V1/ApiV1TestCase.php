<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

/**
 * Base test case for API v1 feature tests.
 *
 * Disables CSRF verification for API requests so that token-based (Bearer)
 * authentication works in tests. The application treats requests from stateful
 * domains (e.g. localhost) as stateful and would otherwise apply CSRF, causing 419.
 */
abstract class ApiV1TestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }
}
