<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function getNotifications(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Return both unread and read notifications
        // return response()->json([
        //     'unread_notifications' => $user->unreadNotifications,
        //     'read_notifications' => $user->notifications()->whereNotNull('read_at')->get(),
        // ]);
        return response()->json([
            'notifications' => $user->unreadNotifications,
        ]);
    }

    public function markAsRead(Request $request, $id): JsonResponse
    {
        $user = Auth::user();
        $notification = $user->notifications()->find($id);

        if ($notification) {
            $notification->markAsRead();
            return response()->json(['message' => 'Notification marked as read.'],200);
        }

        return response()->json(['message' => 'Notification not found.'], 404);
    }
}
