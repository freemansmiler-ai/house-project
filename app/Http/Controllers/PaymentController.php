<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PaymentController extends Controller
{
    /**
     * Display a listing of payments for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $payments = Payment::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $payments
        ]);
    }

    /**
     * Process a simulated payment (subscription or booking fee).
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'booking_id' => 'nullable|exists:bookings,id',
            'subscription_id' => 'nullable|exists:subscriptions,id',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|max:10',
            'payment_method' => 'required|string|max:50', // e.g. Mobile Money, Card
            'transaction_reference' => 'nullable|string|max:100|unique:payments,transaction_reference',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Create Payment record
        $payment = Payment::create([
            'user_id' => $user->id,
            'booking_id' => $request->booking_id,
            'subscription_id' => $request->subscription_id,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'payment_method' => $request->payment_method,
            'transaction_reference' => $request->transaction_reference ?? 'PHG-' . strtoupper(uniqid()),
            'status' => 'successful',
            'paid_at' => now()
        ]);

        // Dispatch Payment Notification
        NotificationService::send(
            $user,
            'Payment Processed Successfully',
            "Thank you! Your payment of {$payment->amount} {$payment->currency} via {$payment->payment_method} has been received. Transaction Ref: {$payment->transaction_reference}.",
            'payment_received',
            ['payment_id' => $payment->id]
        );

        return response()->json([
            'success' => true,
            'message' => 'Simulated payment processed successfully.',
            'data' => $payment
        ], 201);
    }
}
