<?php

namespace App\Http\Controllers;

use App\Models\ContactMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ContactMessageController extends Controller
{
    /**
     * Display a listing of contact messages (Admin only).
     */
    public function index(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $query = ContactMessage::query();

        if ($status) {
            $query->where('status', $status);
        }

        $messages = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $messages
        ]);
    }

    /**
     * Store a newly created contact message in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'subject' => 'required|string|max:255',
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $contactMessage = ContactMessage::create([
            'name' => $request->name,
            'email' => $request->email,
            'subject' => $request->subject,
            'message' => $request->message,
            'status' => 'unread'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Your message has been sent successfully. We will get back to you shortly.',
            'data' => $contactMessage
        ], 201);
    }

    /**
     * Update the status of a contact message (Admin only).
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $contactMessage = ContactMessage::find($id);

        if (!$contactMessage) {
            return response()->json([
                'success' => false,
                'message' => 'Contact message not found.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:unread,read,resolved',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $contactMessage->update([
            'status' => $request->status
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Message status updated successfully.',
            'data' => $contactMessage
        ]);
    }

    /**
     * Remove the specified contact message from storage (Admin only).
     */
    public function destroy($id): JsonResponse
    {
        $contactMessage = ContactMessage::find($id);

        if (!$contactMessage) {
            return response()->json([
                'success' => false,
                'message' => 'Contact message not found.'
            ], 404);
        }

        $contactMessage->delete();

        return response()->json([
            'success' => true,
            'message' => 'Contact message deleted successfully.'
        ]);
    }
}
