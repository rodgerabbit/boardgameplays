<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\GroupService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to throttle group creation.
 *
 * Enforces 1 group creation per 5 minutes per user using Redis cache.
 */
class ThrottleGroupCreation
{
    /**
     * Create a new middleware instance.
     */
    public function __construct(
        private readonly GroupService $groupService,
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            abort(401, 'Unauthenticated.');
        }

        if ($this->groupService->checkCreateRateLimit($user)) {
            return response()->json([
                'message' => 'You have exceeded the rate limit for creating groups. Please wait before creating another group.',
            ], 429);
        }

        // Rate limit not exceeded, allow request to proceed
        // The cache will be set by checkCreateRateLimit if not already set
        return $next($request);
    }
}
