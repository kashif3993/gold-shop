<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    /**
     * Show the password reset page.
     */
    public function create(Request $request): Response
    {
        return Inertia::render('auth/forgot-password', [
            'status' => $request->session()->get('status'),
            'verifiedEmail' => $request->session()->get('verifiedEmail'),
        ]);
    }

    /**
     * Step 1: look up the account by email. If it exists, flash the email back
     * so the page can show the new-password step; otherwise return an error.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => 'required|email']);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'No account was found with that email address.',
            ]);
        }

        return back()->with('verifiedEmail', $user->email);
    }

    /**
     * Step 2: set the new password directly for the verified email.
     *
     * @throws ValidationException
     */
    public function reset(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => 'required|email',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => 'No account was found with that email address.',
            ]);
        }

        $user->forceFill([
            'password_hash' => $request->password,
        ])->save();

        return to_route('login')->with('status', 'Your password has been reset. Please log in.');
    }
}
