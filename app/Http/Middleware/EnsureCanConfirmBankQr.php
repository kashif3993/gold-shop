<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admins can always confirm a Bank QR payment; a manager only once an admin
 * has turned that on in Settings (see User::canConfirmBankQr()). Separate
 * from the blanket 'admin' middleware so this one action can be opened up
 * to managers without exposing the rest of the admin area to them.
 */
class EnsureCanConfirmBankQr
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $request->user()?->canConfirmBankQr(),
            403,
            'Only an admin, or a manager granted this permission in Settings, can confirm Bank QR payments.',
        );

        return $next($request);
    }
}
