<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;

class NotificationController extends Controller
{
    public function getNotifications(Request $request): JsonResponse
    {
        $user = Auth::user();

        // Return both unread and read notifications
        return response()->json($user->notifications()->select('id', 'data','read_at','created_at')->get(),200);
        // return response()->json([
        //     'notifications' => $user->unreadNotifications,
        // ]);
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


    public function broadcast(Request $request)
    {
        $response = Broadcast::auth($request);
    
        // Decode the default response to modify channel_data format
        $responseData = json_decode($response->getContent(), true);
    
        if (isset($responseData['channel_data'])) {
            // Ensure channel_data is decoded properly
            $responseData['channel_data'] = json_decode($responseData['channel_data'], true);
        }
    
        return response()->json($responseData);

    }
}
