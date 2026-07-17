<?php

namespace App\Http\Controllers;

use App\Models\SupportTicket;
use App\Models\SupportTicketReply;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Traits\LogsActivity;

class SupportTicketController extends Controller
{
    use LogsActivity;
    /**
     * Display a listing of tickets for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $tickets = SupportTicket::where('user_id', $user->id)
            ->orderBy('updated_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tickets
        ]);
    }

    /**
     * Store a newly created ticket.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'subject' => 'required|string|max:255',
            'description' => 'required|string',
            'category' => 'required|string|max:100',
            'priority' => 'nullable|in:low,medium,high',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        
        // Generate unique ticket number
        do {
            $ticketNumber = 'TIC-' . strtoupper(Str::random(8));
        } while (SupportTicket::where('ticket_number', $ticketNumber)->exists());

        $ticket = SupportTicket::create([
            'user_id' => $user->id,
            'ticket_number' => $ticketNumber,
            'subject' => $request->subject,
            'description' => $request->description,
            'category' => $request->category,
            'priority' => $request->priority ?? 'medium',
            'status' => 'open'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Support ticket created successfully.',
            'data' => $ticket
        ], 201);
    }

    /**
     * Display the specified ticket with its replies.
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $ticket = SupportTicket::with(['user.profile'])->find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Support ticket not found.'
            ], 404);
        }

        // Authorize: user must own the ticket OR be an admin
        if ($ticket->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized access to this ticket.'
            ], 403);
        }

        // Load replies with user and profile details
        $replies = SupportTicketReply::where('support_ticket_id', $ticket->id)
            ->with(['user.profile'])
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'ticket' => $ticket,
                'replies' => $replies
            ]
        ]);
    }

    /**
     * Reply to the ticket (for ticket owner or admin).
     */
    public function reply(Request $request, $id): JsonResponse
    {
        $ticket = SupportTicket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Support ticket not found.'
            ], 404);
        }

        $user = $request->user();

        // Authorize: user must own the ticket OR be an admin
        if ($ticket->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $reply = SupportTicketReply::create([
            'support_ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'message' => $request->message
        ]);

        // If the reply is from an admin, we might want to update status to in_progress or resolved, or open if user replies
        if ($user->role === 'admin') {
            $ticket->update(['status' => 'in_progress']);
        } else {
            // If user replies, set status back to open if it was closed or resolved (or just keep open)
            if ($ticket->status === 'resolved' || $ticket->status === 'closed') {
                $ticket->update(['status' => 'open']);
            }
        }

        // Touch the ticket to update its updated_at timestamp
        $ticket->touch();

        // Load relations for response
        $reply->load(['user.profile']);

        return response()->json([
            'success' => true,
            'message' => 'Reply posted successfully.',
            'data' => $reply
        ], 201);
    }

    /**
     * Close a ticket (for ticket owner or admin).
     */
    public function close(Request $request, $id): JsonResponse
    {
        $ticket = SupportTicket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Support ticket not found.'
            ], 404);
        }

        $user = $request->user();

        if ($ticket->user_id !== $user->id && $user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized.'
            ], 403);
        }

        $ticket->update(['status' => 'closed']);

        return response()->json([
            'success' => true,
            'message' => 'Ticket closed successfully.',
            'data' => $ticket
        ]);
    }

    /**
     * Admin view of all tickets.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $priority = $request->query('priority');
        $category = $request->query('category');

        $query = SupportTicket::with(['user.profile']);

        if ($status) {
            $query->where('status', $status);
        }
        if ($priority) {
            $query->where('priority', $priority);
        }
        if ($category) {
            $query->where('category', $category);
        }

        $tickets = $query->orderBy('updated_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $tickets
        ]);
    }

    /**
     * Admin update status of any ticket.
     */
    public function adminUpdateStatus(Request $request, $id): JsonResponse
    {
        $ticket = SupportTicket::find($id);

        if (!$ticket) {
            return response()->json([
                'success' => false,
                'message' => 'Support ticket not found.'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:open,in_progress,resolved,closed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $oldStatus = $ticket->status;
        $ticket->update([
            'status' => $request->status
        ]);

        $this->logActivity('admin_ticket_status_update', "Admin updated status of ticket ID: {$id} ({$ticket->ticket_number}) from '{$oldStatus}' to '{$request->status}'");

        return response()->json([
            'success' => true,
            'message' => 'Ticket status updated successfully.',
            'data' => $ticket
        ]);
    }
}
