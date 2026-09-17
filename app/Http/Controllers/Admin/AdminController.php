<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Inertia\Inertia;

class AdminController extends Controller
{
    /** Landing page linking to the admin-only screens. */
    public function index()
    {
        return Inertia::render('admin/index');
    }
}
