<?php

namespace App\Http\Controllers;

use App\Models\Subscription;
use App\Models\Payment;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Carbon;

class SubscriptionController extends Controller
{
    /**
     * Get the active subscription for the authenticated user.
     */
    public function active(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $subscription = Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->where('ends_at', '>', now())
            ->orderBy('created_at', 'desc')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $subscription
        ]);
    }

    /**
     * Subscribe the authenticated user to a billing plan.
     */
    public function subscribe(Request $request): JsonResponse
    {
        $user = $request->user();

        // Ensure user is Landlord or Agent
        if (!in_array($user->role, ['landlord', 'agent'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only landlords and agents can purchase subscriptions.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'plan_name' => 'required|string|max:50',
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|max:10',
            'payment_method' => 'required|string|max:50',
            'transaction_reference' => 'required|string|unique:payments,transaction_reference',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Determine plan limits
        $propertyLimit = 5;
        $featuredLimit = 1;
        $plan = strtolower($request->plan_name);

        if (strpos($plan, 'premium') !== false) {
            $propertyLimit = 100;
            $featuredLimit = 10;
        } elseif (strpos($plan, 'enterprise') !== false) {
            $propertyLimit = 1000;
            $featuredLimit = 100;
        }

        // Deactivate all previous active subscriptions
        Subscription::where('user_id', $user->id)
            ->where('status', 'active')
            ->update(['status' => 'expired']);

        // Create subscription
        $subscription = Subscription::create([
            'user_id' => $user->id,
            'plan_name' => $request->plan_name,
            'plan_price' => $request->amount,
            'property_limit' => $propertyLimit,
            'featured_limit' => $featuredLimit,
            'starts_at' => now(),
            'ends_at' => Carbon::now()->addMonth(),
            'status' => 'active'
        ]);

        // Create payment
        $payment = Payment::create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'amount' => $request->amount,
            'currency' => $request->currency,
            'payment_method' => $request->payment_method,
            'transaction_reference' => $request->transaction_reference,
            'status' => 'successful',
            'paid_at' => now()
        ]);

        // Dispatch subscription notification
        NotificationService::send(
            $user,
            'Subscription Activated',
            "Your {$subscription->plan_name} subscription has been successfully activated (Limits: {$propertyLimit} properties). Transaction Ref: {$payment->transaction_reference}.",
            'subscription_activated',
            ['subscription_id' => $subscription->id, 'payment_id' => $payment->id]
        );

        return response()->json([
            'success' => true,
            'message' => 'Subscription activated successfully.',
            'subscription' => $subscription,
            'payment' => $payment
        ], 201);
    }
}
