<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;

/**
 * Ensures Sanctum treats API requests as stateful when the request Host is in
 * stateful domains but Referer/Origin are missing (e.g. same-origin XHR without Origin).
 */
class EnsureSanctumStatefulFromHost
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->headers->get('referer') !== null || $request->headers->get('origin') !== null) {
            return $next($request);
        }

        $host = $request->getHttpHost();
        $stateful = array_filter(config('sanctum.stateful', []));

        foreach ($stateful as $domain) {
            $domain = trim((string) $domain);
            if ($domain === '') {
                continue;
            }
            if ($domain === Sanctum::$currentRequestHostPlaceholder) {
                $request->headers->set('Referer', $request->url());
                return $next($request);
            }
            if (Str::lower($host) === Str::lower($domain)) {
                $request->headers->set('Referer', $request->url());
                return $next($request);
            }
        }

        return $next($request);
    }
}
