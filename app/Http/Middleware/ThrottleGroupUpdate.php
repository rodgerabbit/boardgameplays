<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Group;
use App\Services\GroupService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware to throttle group updates.
 *
 * Enforces 1 group update per 10 seconds per user per group using Redis cache.
 */
class ThrottleGroupUpdate
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

        $groupId = $request->route('id');
        if ($groupId === null) {
            return $next($request);
        }

        $group = Group::find($groupId);
        if ($group === null) {
            return $next($request);
        }

        if ($this->groupService->checkUpdateRateLimit($user, $group)) {
            return response()->json([
                'message' => 'You have exceeded the rate limit for updating groups. Please wait before updating again.',
            ], 429);
        }

        return $next($request);
    }
}
