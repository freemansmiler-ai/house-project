<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Display a listing of the user's notifications.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $notifications = Notification::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $notifications
        ]);
    }

    /**
     * Mark a specific notification as read.
     */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $notification = Notification::where('user_id', $user->id)->find($id);

        if (!$notification) {
            return response()->json([
                'success' => false,
                'message' => 'Notification not found.'
            ], 404);
        }

        $notification->read_at = now();
        $notification->save();

        return response()->json([
            'success' => true,
            'message' => 'Notification marked as read.',
            'data' => $notification
        ]);
    }

    /**
     * Mark all notifications of the user as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        Notification::where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'success' => true,
            'message' => 'All notifications marked as read.'
        ]);
    }

    /**
     * Register browser device token for push notification emulation.
     */
    public function deviceToken(Request $request): JsonResponse
    {
        $request->validate([
            'device_token' => 'required|string|max:500'
        ]);

        $user = $request->user();
        $user->device_token = $request->device_token;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Device token updated successfully.'
        ]);
    }

    /**
     * Pull pending push notifications from Cache and clear them.
     */
    public function pullPushes(Request $request): JsonResponse
    {
        $user = $request->user();
        $cacheKey = "pending_pushes:{$user->id}";

        $pushes = \Illuminate\Support\Facades\Cache::get($cacheKey, []);
        \Illuminate\Support\Facades\Cache::forget($cacheKey);

        return response()->json([
            'success' => true,
            'data' => $pushes
        ]);
    }
}
