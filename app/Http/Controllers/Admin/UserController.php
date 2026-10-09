<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        return Inertia::render('admin/users/index', [
            'users' => User::orderBy('username')->get(['id', 'username', 'email', 'role', 'is_active', 'created_at']),
        ]);
    }

    /** Promote/demote a staff account. An admin can't change their own role — avoids locking everyone out by accident. */
    public function updateRole(Request $request, User $user)
    {
        $data = $request->validate([
            'role' => 'required|in:admin,manager,shopkeeper',
        ]);

        if ($user->id === $request->user()->id) {
            return back()->with('error', "You can't change your own role.");
        }

        $user->update(['role' => $data['role']]);

        return back()->with('success', "{$user->username} is now {$data['role']}.");
    }

    /** Deactivate/reactivate a login. An admin can't deactivate themselves. */
    public function toggleActive(Request $request, User $user)
    {
        if ($user->id === $request->user()->id) {
            return back()->with('error', "You can't deactivate your own account.");
        }

        $user->update(['is_active' => ! $user->is_active]);

        return back()->with('success', $user->is_active ? "{$user->username} reactivated." : "{$user->username} deactivated.");
    }
}
