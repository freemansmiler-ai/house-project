<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Property;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class BookingController extends Controller
{
    /**
     * Display a listing of bookings for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->role === 'landlord' || $user->role === 'agent') {
            // Fetch bookings received by the landlord/agent
            $bookings = Booking::with(['property.images', 'user', 'payments'])
                ->whereHas('property', function ($query) use ($user) {
                    $query->where('user_id', $user->id);
                })
                ->orderBy('booking_date', 'asc')
                ->get();
        } else {
            // Fetch bookings requested by the tenant
            $bookings = Booking::with(['property.images', 'property.user', 'payments'])
                ->where('user_id', $user->id)
                ->orderBy('booking_date', 'asc')
                ->get();
        }

        return response()->json([
            'success' => true,
            'data' => $bookings
        ]);
    }

    /**
     * Store a newly created booking in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'booking_date' => 'required|date|after_or_equal:today',
            'time_slot' => 'nullable|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Check if property is active
        $property = Property::find($request->property_id);
        if ($property->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'This property is not currently accepting viewings.'
            ], 400);
        }

        $booking = Booking::create([
            'property_id' => $request->property_id,
            'user_id' => $user->id,
            'booking_date' => $request->booking_date,
            'time_slot' => $request->time_slot,
            'status' => 'pending',
            'notes' => $request->notes,
        ]);

        // Dispatch system notification to landlord/agent
        $publisher = User::find($property->user_id);
        if ($publisher) {
            NotificationService::send(
                $publisher,
                'New Inspection Booking Request',
                "Tenant {$user->name} has requested a viewing for '{$property->title}' on {$request->booking_date} during {$request->time_slot}.",
                'booking_request',
                ['booking_id' => $booking->id]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Viewing appointment requested successfully! The agent will confirm soon.',
            'data' => $booking->load('property')
        ], 211);
    }

    /**
     * Update status of an inspection booking (Accept/Reject).
     * Accessible by the owner of the property (landlord/agent).
     */
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|in:confirmed,cancelled',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $booking = Booking::with('property')->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking request not found.'
            ], 404);
        }

        if ($booking->property->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You do not own this property.'
            ], 403);
        }

        $booking->status = $request->status;
        $booking->save();

        // Dispatch system notification to the tenant
        $tenant = User::find($booking->user_id);
        if ($tenant) {
            NotificationService::send(
                $tenant,
                'Viewing Appointment Update',
                "Your viewing appointment request for '{$booking->property->title}' has been {$request->status} by the associate.",
                'booking_update',
                ['booking_id' => $booking->id]
            );
        }

        return response()->json([
            'success' => true,
            'message' => "Booking status updated to {$request->status} and tenant notified.",
            'data' => $booking
        ]);
    }

    /**
     * Reschedule an inspection booking (change date / time slot).
     * Accessible by the owner of the property (landlord/agent).
     */
    public function reschedule(Request $request, int $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'booking_date' => 'required|date|after_or_equal:today',
            'time_slot' => 'required|string|max:255',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $booking = Booking::with('property')->find($id);

        if (!$booking) {
            return response()->json([
                'success' => false,
                'message' => 'Booking request not found.'
            ], 404);
        }

        if ($booking->property->user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. You do not own this property.'
            ], 403);
        }

        $booking->booking_date = $request->booking_date;
        $booking->time_slot = $request->time_slot;
        if ($request->has('notes')) {
            $booking->notes = $request->notes;
        }
        $booking->status = 'pending'; // Reset status to pending
        $booking->save();

        // Dispatch system notification to the tenant
        $tenant = User::find($booking->user_id);
        if ($tenant) {
            NotificationService::send(
                $tenant,
                'Viewing Appointment Rescheduled',
                "Your viewing appointment request for '{$booking->property->title}' has been proposed for a new slot: {$request->booking_date} at {$request->time_slot} by the associate.",
                'booking_reschedule',
                ['booking_id' => $booking->id]
            );
        }

        return response()->json([
            'success' => true,
            'message' => "Booking rescheduled successfully to {$request->booking_date} and tenant notified.",
            'data' => $booking->load(['property', 'user'])
        ]);
    }
}
