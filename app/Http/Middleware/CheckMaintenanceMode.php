<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shop-wide "closed for maintenance" switch (Admin → Settings), distinct from
 * Laravel's own file-based `php artisan down`. Guests still reach the login
 * page (so an admin can sign in) and admins are never blocked (so whoever
 * turned it on can always turn it back off); every other signed-in user sees
 * the maintenance page instead of the app.
 */
class CheckMaintenanceMode
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Setting::bool('maintenance_mode_enabled')) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user || $user->isAdmin()) {
            return $next($request);
        }

        if ($request->routeIs('login') || $request->routeIs('logout')) {
            return $next($request);
        }

        return Inertia::render('maintenance', [
            'message' => Setting::get('maintenance_message') ?: 'The shop system is temporarily down for maintenance. Please check back shortly.',
        ])->toResponse($request)->setStatusCode(503);
    }
}
