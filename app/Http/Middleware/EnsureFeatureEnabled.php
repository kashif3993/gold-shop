<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks a route when its feature toggle (Admin → Settings) is off, e.g.
 * `Route::middleware('feature:buyback')`. Backend enforcement is what actually
 * matters — the frontend hides the corresponding nav link / UI section using
 * the same `{feature}_enabled` value shared via HandleInertiaRequests, but a
 * disabled feature must not be reachable just by knowing/typing the URL.
 */
class EnsureFeatureEnabled
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless(
            Setting::bool("{$feature}_enabled", true),
            403,
            'This feature is currently turned off. An admin can re-enable it from Settings.',
        );

        return $next($request);
    }
}
