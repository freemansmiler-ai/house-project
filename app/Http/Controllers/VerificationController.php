<?php

namespace App\Http\Controllers;

use App\Models\VerificationRequest;
use App\Models\User;
use App\Services\NotificationService;
use App\Models\Agent;
use App\Models\Landlord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use App\Traits\LogsActivity;

class VerificationController extends Controller
{
    use LogsActivity;
    /**
     * Submit a verification request.
     */
    public function submit(Request $request): JsonResponse
    {
        $user = $request->user();

        if (!in_array($user->role, ['landlord', 'agent'])) {
            return response()->json([
                'success' => false,
                'message' => 'Only landlords and agents can submit verification requests.'
            ], 403);
        }

        // Check if there is already an active pending request
        $existing = VerificationRequest::where('user_id', $user->id)
            ->where('status', 'pending')
            ->first();

        if ($existing) {
            return response()->json([
                'success' => false,
                'message' => 'You already have a pending verification request.'
            ], 400);
        }

        $validator = Validator::make($request->all(), [
            'business_license' => 'required|file|mimes:jpeg,png,jpg,pdf|max:10240',
            'national_id' => 'required|file|mimes:jpeg,png,jpg,pdf|max:10240',
            'selfie' => 'required|file|mimes:jpeg,png,jpg|max:10240',
            'business_address' => 'required|string|max:1000',
            'document_type' => 'nullable|string|max:255',
            'document_number' => 'nullable|string|max:255'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Save files
        $licensePath = '';
        $nationalIdPath = '';
        $selfiePath = '';

        if ($request->hasFile('business_license')) {
            $path = $request->file('business_license')->store('verifications', 'public');
            $licensePath = asset('storage/' . $path);
        }

        if ($request->hasFile('national_id')) {
            $path = $request->file('national_id')->store('verifications', 'public');
            $nationalIdPath = asset('storage/' . $path);
        }

        if ($request->hasFile('selfie')) {
            $path = $request->file('selfie')->store('verifications', 'public');
            $selfiePath = asset('storage/' . $path);
        }

        // Create request
        $verificationRequest = VerificationRequest::create([
            'user_id' => $user->id,
            'role_requested' => $user->role,
            'document_type' => $request->input('document_type', 'National ID'),
            'document_number' => $request->input('document_number', 'N/A'),
            'document_file_path' => $nationalIdPath, // map to existing column
            'business_license_path' => $licensePath,
            'national_id_path' => $nationalIdPath,
            'selfie_path' => $selfiePath,
            'business_address' => $request->business_address,
            'status' => 'pending'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Verification request submitted successfully. It will be reviewed by admin soon.',
            'data' => $verificationRequest
        ], 201);
    }

    /**
     * Get the latest verification status for the authenticated user.
     */
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $latest = VerificationRequest::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $latest
        ]);
    }

    /**
     * Admin: Get all verification requests.
     */
    public function adminIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $status = $request->query('status');
        $query = VerificationRequest::with('user');

        if ($status) {
            $query->where('status', $status);
        }

        // Get the requests, then sort by user subscription tier (Enterprise > Premium > Basic > Free)
        $requests = $query->get()->sortByDesc(function ($req) {
            $reqUser = $req->user;
            if (!$reqUser) return 0;

            $activeSub = $reqUser->subscriptions()
                ->where('status', 'active')
                ->where('ends_at', '>', now())
                ->first();

            if (!$activeSub) return 0; // Free / no plan

            $plan = strtolower($activeSub->plan_name);
            if (strpos($plan, 'enterprise') !== false) return 3;
            if (strpos($plan, 'premium') !== false) return 2;
            if (strpos($plan, 'basic') !== false) return 1;
            return 0;
        })->values();

        return response()->json([
            'success' => true,
            'data' => $requests
        ]);
    }

    /**
     * Admin: Approve a verification request.
     */
    public function adminApprove(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        if ($admin->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $verificationRequest = VerificationRequest::find($id);

        if (!$verificationRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Verification request not found.'
            ], 404);
        }

        if ($verificationRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been reviewed.'
            ], 400);
        }

        $verificationRequest->status = 'approved';
        $verificationRequest->reviewed_by = $admin->id;
        $verificationRequest->reviewed_at = now();
        $verificationRequest->save();

        $this->logActivity('admin_verification_approve', "Admin approved verification request ID: {$id} for user ID: {$verificationRequest->user_id}");

        // Activate profile status depending on role
        if ($verificationRequest->role_requested === 'agent') {
            $agent = Agent::where('user_id', $verificationRequest->user_id)->first();
            if ($agent) {
                $agent->is_verified = true;
                $agent->save();
            } else {
                Agent::create([
                    'user_id' => $verificationRequest->user_id,
                    'is_verified' => true,
                    'rating' => 5.00
                ]);
            }
        } elseif ($verificationRequest->role_requested === 'landlord') {
            $landlord = Landlord::where('user_id', $verificationRequest->user_id)->first();
            if ($landlord) {
                $landlord->is_verified = true;
                $landlord->save();
            } else {
                Landlord::create([
                    'user_id' => $verificationRequest->user_id,
                    'is_verified' => true
                ]);
            }
        }

        // Notify user
        $targetUser = User::find($verificationRequest->user_id);
        if ($targetUser) {
            NotificationService::send(
                $targetUser,
                'Profile Verification Approved',
                'Congratulations! Your associate profile has been approved. You are now verified and can publish listings.',
                'verification_approved',
                ['request_id' => $verificationRequest->id]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification request approved successfully.',
            'data' => $verificationRequest
        ]);
    }

    /**
     * Admin: Reject a verification request.
     */
    public function adminReject(Request $request, int $id): JsonResponse
    {
        $admin = $request->user();
        if ($admin->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'rejection_reason' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $verificationRequest = VerificationRequest::find($id);

        if (!$verificationRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Verification request not found.'
            ], 404);
        }

        if ($verificationRequest->status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'This request has already been reviewed.'
            ], 400);
        }

        $verificationRequest->status = 'rejected';
        $verificationRequest->rejection_reason = $request->rejection_reason;
        $verificationRequest->reviewed_by = $admin->id;
        $verificationRequest->reviewed_at = now();
        $verificationRequest->save();

        $this->logActivity('admin_verification_reject', "Admin rejected verification request ID: {$id} for user ID: {$verificationRequest->user_id}. Reason: {$request->rejection_reason}");

        // Deactivate profile verification if it was somehow true
        if ($verificationRequest->role_requested === 'agent') {
            $agent = Agent::where('user_id', $verificationRequest->user_id)->first();
            if ($agent) {
                $agent->is_verified = false;
                $agent->save();
            }
        } elseif ($verificationRequest->role_requested === 'landlord') {
            $landlord = Landlord::where('user_id', $verificationRequest->user_id)->first();
            if ($landlord) {
                $landlord->is_verified = false;
                $landlord->save();
            }
        }

        // Notify user
        $targetUser = User::find($verificationRequest->user_id);
        if ($targetUser) {
            NotificationService::send(
                $targetUser,
                'Profile Verification Rejected',
                'Your associate profile verification request was rejected. Reason: ' . $request->rejection_reason,
                'verification_rejected',
                ['request_id' => $verificationRequest->id]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Verification request rejected.',
            'data' => $verificationRequest
        ]);
    }
}
