<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

/**
 * Base controller class for all controllers.
 *
 * This class provides a foundation for controllers in the application.
 * All controllers should extend this base class to maintain consistency.
 */
abstract class Controller
{
    use AuthorizesRequests;
}
