<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\User;
use App\Models\Property;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ReviewController extends Controller
{
    /**
     * Display a listing of reviews filtered by targets.
     */
    public function index(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'property_id' => 'nullable|integer|exists:properties,id',
            'landlord_id' => 'nullable|integer|exists:users,id',
            'agent_id' => 'nullable|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $query = Review::with(['user.profile', 'property', 'landlord.profile', 'agent.profile']);

        if ($request->has('property_id')) {
            $query->where('property_id', $request->property_id);
        } elseif ($request->has('landlord_id')) {
            $query->where('landlord_id', $request->landlord_id);
        } elseif ($request->has('agent_id')) {
            $query->where('agent_id', $request->agent_id);
        } else {
            // By default return all approved reviews
            $query->where('is_approved', true);
        }

        $reviews = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $reviews
        ]);
    }

    /**
     * Store a new review in storage.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        // 1. Enforce Role: Tenant
        if ($user->role !== 'tenant') {
            return response()->json([
                'success' => false,
                'message' => 'Only tenants can submit reviews.'
            ], 403);
        }

        // 2. Enforce Verification status (OTP verified)
        if (is_null($user->email_verified_at) && is_null($user->phone_verified_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Only verified tenants can submit reviews. Please verify your email or phone contact first.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:2000',
            'property_id' => 'nullable|integer|exists:properties,id',
            'landlord_id' => 'nullable|integer|exists:users,id',
            'agent_id' => 'nullable|integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Must target exactly one reviewee type
        $targetsCount = 0;
        if ($request->filled('property_id')) $targetsCount++;
        if ($request->filled('landlord_id')) $targetsCount++;
        if ($request->filled('agent_id')) $targetsCount++;

        if ($targetsCount !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'You must review exactly one target (either a property, a landlord, or an agent).'
            ], 400);
        }

        // If target is landlord, ensure the target is actually a landlord
        if ($request->filled('landlord_id')) {
            $landlord = User::find($request->landlord_id);
            if (!$landlord || $landlord->role !== 'landlord') {
                return response()->json([
                    'success' => false,
                    'message' => 'Target user is not a landlord.'
                ], 400);
            }
        }

        // If target is agent, ensure the target is actually an agent
        if ($request->filled('agent_id')) {
            $agent = User::find($request->agent_id);
            if (!$agent || $agent->role !== 'agent') {
                return response()->json([
                    'success' => false,
                    'message' => 'Target user is not an agent.'
                ], 400);
            }
        }

        // Create the review
        $review = Review::create([
            'user_id' => $user->id,
            'property_id' => $request->property_id,
            'landlord_id' => $request->landlord_id,
            'agent_id' => $request->agent_id,
            'rating' => $request->rating,
            'comment' => $request->comment,
            'is_approved' => true
        ]);

        // Recalculate average rating of target if agent
        if ($request->filled('agent_id')) {
            $agentUser = User::find($request->agent_id);
            $avgRating = Review::where('agent_id', $agentUser->id)->avg('rating');
            $agentProfile = $agentUser->agent;
            if ($agentProfile) {
                $agentProfile->rating = round($avgRating, 2);
                $agentProfile->save();
            }

            // Dispatch notification
            NotificationService::send(
                $agentUser,
                'New Review Received',
                "Tenant {$user->name} left you a {$request->rating}-star review.",
                'review_received',
                ['review_id' => $review->id]
            );
        }

        // Recalculate average rating if landlord
        if ($request->filled('landlord_id')) {
            $landlordUser = User::find($request->landlord_id);
            
            // Dispatch notification
            NotificationService::send(
                $landlordUser,
                'New Review Received',
                "Tenant {$user->name} left you a {$request->rating}-star review.",
                'review_received',
                ['review_id' => $review->id]
            );
        }

        // Dispatch notification if property
        if ($request->filled('property_id')) {
            $property = Property::find($request->property_id);
            $owner = $property->user;
            if ($owner) {
                NotificationService::send(
                    $owner,
                    'New Property Review',
                    "Tenant {$user->name} left a {$request->rating}-star review on your property: {$property->title}.",
                    'review_received',
                    ['review_id' => $review->id]
                );
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Review submitted successfully!',
            'data' => $review->load('user.profile')
        ], 201);
    }
}
