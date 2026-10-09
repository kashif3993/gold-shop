<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /** Polled by the header bell (see NotificationBell) — computed fresh every call, nothing is ever stored or marked read. */
    public function index(Request $request, NotificationService $notifications): JsonResponse
    {
        return response()->json([
            'notifications' => $notifications->for($request->user()),
        ]);
    }
}
