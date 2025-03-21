<?php

namespace App\Http\Controllers;

use App\Events\NotificationSent;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;

class NotificationController extends Controller
{
    public function getNotifications(Request $request): JsonResponse
    {
        $user = Auth::user();

        $perpage = $request->query('perpage', 10);
        $filter = $request->input('filter');

        $query = $user->notifications()->select('id', 'data','read_at','created_at');

        if ($filter === 'unread') {
            $query->whereNull('read_at');
        }elseif ($filter === 'read') {
            $query->whereNotNull('read_at');
        }

        $data = $query->orderBy('created_at', 'desc')->paginate($perpage);

        return response()->json($data,200);
        
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


    public function broadcast(Request $request): JsonResponse
    {
        try{
            $user = Auth::user();
            $response = Broadcast::auth($request);
    
            // Ensure response is always an array
            $responseData = is_array($response) ? $response : json_decode($response->getContent(), true);
        
            if (isset($responseData['channel_data']) && is_string($responseData['channel_data'])) {
                // Decode channel_data only if it is a string
                $responseData['channel_data'] = json_decode($responseData['channel_data'], true);
            }
        
            return response()->json($responseData);
        }catch(Exception $e){
            return response()->json(['error' => 'Unable to broadcast'], 400);
        }
    }
}
