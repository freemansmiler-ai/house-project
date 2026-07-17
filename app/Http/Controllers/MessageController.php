<?php

namespace App\Http\Controllers;

use App\Models\Message;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class MessageController extends Controller
{
    /**
     * Get list of active conversations for the current user.
     */
    public function conversations(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        // Fetch user IDs of all people who sent messages to or received messages from this user
        $senderIds = Message::where('receiver_id', $userId)->pluck('sender_id')->toArray();
        $receiverIds = Message::where('sender_id', $userId)->pluck('receiver_id')->toArray();
        $contactIds = array_unique(array_merge($senderIds, $receiverIds));

        $conversations = [];

        foreach ($contactIds as $contactId) {
            $contact = User::find($contactId);
            if (!$contact) continue;

            // Get the last message in the thread
            $lastMessage = Message::where(function ($query) use ($userId, $contactId) {
                $query->where('sender_id', $userId)->where('receiver_id', $contactId);
            })->orWhere(function ($query) use ($userId, $contactId) {
                $query->where('sender_id', $contactId)->where('receiver_id', $userId);
            })->orderBy('created_at', 'desc')->first();

            // Count unread messages sent by the contact to the current user
            $unreadCount = Message::where('sender_id', $contactId)
                ->where('receiver_id', $userId)
                ->whereNull('read_at')
                ->count();

            // Check if contact is typing
            $isTyping = Cache::has("typing:{$contactId}:{$userId}");

            $conversations[] = [
                'contact' => [
                    'id' => $contact->id,
                    'name' => $contact->name,
                    'email' => $contact->email,
                    'role' => $contact->role,
                    'avatar' => $contact->avatar_url ?? null
                ],
                'last_message' => $lastMessage,
                'unread_count' => $unreadCount,
                'is_typing' => $isTyping
            ];
        }

        // Sort conversations by last message timestamp desc
        usort($conversations, function ($a, $b) {
            if (!$a['last_message']) return 1;
            if (!$b['last_message']) return -1;
            return strcmp($b['last_message']->created_at, $a['last_message']->created_at);
        });

        return response()->json([
            'success' => true,
            'data' => $conversations
        ]);
    }

    /**
     * Get chat history between current user and target user.
     */
    public function history(Request $request, int $userId): JsonResponse
    {
        $currentUserId = $request->user()->id;

        // Fetch messages
        $messages = Message::where(function ($query) use ($currentUserId, $userId) {
            $query->where('sender_id', $currentUserId)->where('receiver_id', $userId);
        })->orWhere(function ($query) use ($currentUserId, $userId) {
            $query->where('sender_id', $userId)->where('receiver_id', $currentUserId);
        })->orderBy('created_at', 'asc')->get();

        // Mark received unread messages as read (Read receipts)
        Message::where('sender_id', $userId)
            ->where('receiver_id', $currentUserId)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        // Get typing status of the partner
        $isTyping = Cache::has("typing:{$userId}:{$currentUserId}");

        return response()->json([
            'success' => true,
            'data' => $messages,
            'partner_is_typing' => $isTyping
        ]);
    }

    /**
     * Send a new message.
     */
    public function send(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required_without:image_path|nullable|string',
            'image_path' => 'nullable|string',
            'property_id' => 'nullable|exists:properties,id',
            'booking_id' => 'nullable|exists:bookings,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $message = Message::create([
            'sender_id' => $user->id,
            'receiver_id' => $request->receiver_id,
            'message' => $request->message ?? '',
            'image_path' => $request->image_path,
            'property_id' => $request->property_id,
            'booking_id' => $request->booking_id,
            'read_at' => null
        ]);

        // Clear typing status immediately upon sending
        Cache::forget("typing:{$user->id}:{$request->receiver_id}");

        // Dispatch message notification
        $receiver = User::find($request->receiver_id);
        if ($receiver) {
            NotificationService::send(
                $receiver,
                'New Message Received',
                "{$user->name} sent you a message: " . ($request->image_path ? '📷 [Image Attachment]' : substr($request->message ?? '', 0, 50)),
                'message_received',
                ['message_id' => $message->id, 'sender_id' => $user->id]
            );
        }

        return response()->json([
            'success' => true,
            'data' => $message
        ], 201);
    }

    /**
     * Set typing status for current user sending to receiver.
     */
    public function setTyping(Request $request, int $receiverId): JsonResponse
    {
        $userId = $request->user()->id;
        $isTyping = filter_var($request->input('is_typing'), FILTER_VALIDATE_BOOLEAN);

        if ($isTyping) {
            // Save in cache for 4 seconds
            Cache::put("typing:{$userId}:{$receiverId}", true, now()->addSeconds(4));
        } else {
            Cache::forget("typing:{$userId}:{$receiverId}");
        }

        return response()->json([
            'success' => true,
            'is_typing' => $isTyping
        ]);
    }
}
