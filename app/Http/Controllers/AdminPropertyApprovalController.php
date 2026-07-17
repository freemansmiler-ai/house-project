<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Traits\LogsActivity;

class AdminPropertyApprovalController extends Controller
{
    use LogsActivity;

    /**
     * List all properties waiting for admin audit approval.
     */
    public function pendingList(Request $request): JsonResponse
    {
        $properties = Property::with(['user', 'images', 'amenities'])
            ->whereIn('verification_status', ['pending', 'under_review', 'rejected'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $properties
        ]);
    }

    /**
     * Verify/Approve a property.
     */
    public function approve(Request $request, $id): JsonResponse
    {
        $property = Property::findOrFail($id);

        $property->verification_status = 'verified';
        $property->status = 'active';
        $property->approval_notes = null;
        $property->published_at = now();
        $property->save();

        // Notify user
        $publisher = User::find($property->user_id);
        if ($publisher) {
            NotificationService::send(
                $publisher,
                'Property Listing Verified',
                "Congratulations! Your property listing '{$property->title}' has been verified. It is now live on the public portal.",
                'property_approved',
                ['property_id' => $property->id]
            );
        }

        $this->logActivity('admin_property_approve', "Admin approved property ID: {$id} ('{$property->title}')");

        return response()->json([
            'success' => true,
            'message' => 'Property verified successfully and published live.',
            'data' => $property
        ]);
    }

    /**
     * Reject a property.
     */
    public function reject(Request $request, $id): JsonResponse
    {
        $property = Property::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'notes' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Rejection notes are required.'
            ], 422);
        }

        $property->verification_status = 'rejected';
        $property->status = 'inactive';
        $property->approval_notes = $request->notes;
        $property->save();

        // Notify user
        $publisher = User::find($property->user_id);
        if ($publisher) {
            NotificationService::send(
                $publisher,
                'Property Listing Rejected',
                "Your listing '{$property->title}' was rejected during verification. Reason: {$request->notes}",
                'property_rejected',
                ['property_id' => $property->id]
            );
        }

        $this->logActivity('admin_property_reject', "Admin rejected property ID: {$id} ('{$property->title}') with notes: {$request->notes}");

        return response()->json([
            'success' => true,
            'message' => 'Property rejected successfully.',
            'data' => $property
        ]);
    }

    /**
     * Mark property as Under Review.
     */
    public function underReview(Request $request, $id): JsonResponse
    {
        $property = Property::findOrFail($id);

        $property->verification_status = 'under_review';
        $property->save();

        // Notify user
        $publisher = User::find($property->user_id);
        if ($publisher) {
            NotificationService::send(
                $publisher,
                'Property Listing Under Review',
                "Your property listing '{$property->title}' is now Under Review by our verification team.",
                'property_under_review',
                ['property_id' => $property->id]
            );
        }

        $this->logActivity('admin_property_under_review', "Admin marked property ID: {$id} ('{$property->title}') as Under Review");

        return response()->json([
            'success' => true,
            'message' => 'Property marked as Under Review.',
            'data' => $property
        ]);
    }

    /**
     * Request changes for a property.
     */
    public function requestChanges(Request $request, $id): JsonResponse
    {
        $property = Property::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'notes' => 'required|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Requested changes details are required.'
            ], 422);
        }

        $property->verification_status = 'under_review';
        $property->approval_notes = $request->notes;
        $property->save();

        // Notify user
        $publisher = User::find($property->user_id);
        if ($publisher) {
            NotificationService::send(
                $publisher,
                'Changes Requested on Listing',
                "Verification audit requested modifications for '{$property->title}'. Details: {$request->notes}",
                'property_changes_requested',
                ['property_id' => $property->id]
            );
        }

        $this->logActivity('admin_property_request_changes', "Admin requested changes for property ID: {$id} ('{$property->title}') with notes: {$request->notes}");

        return response()->json([
            'success' => true,
            'message' => 'Changes requested and listing status set to Under Review.',
            'data' => $property
        ]);
    }
}
