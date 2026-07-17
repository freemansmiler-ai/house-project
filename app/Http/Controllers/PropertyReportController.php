<?php

namespace App\Http\Controllers;

use App\Models\Property;
use App\Models\PropertyReport;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PropertyReportController extends Controller
{
    /**
     * Submit a new property report.
     */
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'property_id' => 'required|exists:properties,id',
            'reason' => 'required|string|in:fake_listing,wrong_price,scam,duplicate',
            'details' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $property = Property::findOrFail($request->property_id);

        // Optional: Avoid double reporting from the same user
        $exists = PropertyReport::where('user_id', $user ? $user->id : null)
            ->where('property_id', $property->id)
            ->where('reason', $request->reason)
            ->where('status', 'pending')
            ->exists();

        if ($exists) {
            return response()->json([
                'success' => false,
                'message' => 'You have already submitted a pending report for this property with this reason.'
            ], 400);
        }

        $report = PropertyReport::create([
            'user_id' => $user ? $user->id : null,
            'property_id' => $property->id,
            'reason' => $request->reason,
            'details' => $request->details,
            'status' => 'pending'
        ]);

        // Notify Admins
        $admins = User::where('role', 'admin')->get();
        $reasonLabel = ucfirst(str_replace('_', ' ', $request->reason));
        
        foreach ($admins as $admin) {
            NotificationService::send(
                $admin,
                "Listing Reported: {$reasonLabel}",
                "The property listing '{$property->title}' (ID: {$property->id}) has been reported for '{$reasonLabel}'. Reported by: " . ($user ? $user->name : 'Anonymous'),
                'property_reported',
                ['property_id' => $property->id, 'report_id' => $report->id]
            );
        }

        return response()->json([
            'success' => true,
            'message' => 'Thank you. Your report has been submitted to support team for investigation.',
            'data' => $report
        ]);
    }

    /**
     * Admin: List all property reports.
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

        $reports = PropertyReport::with(['user', 'property.user'])
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $reports
        ]);
    }

    /**
     * Admin: Action a report (resolve/dismiss).
     */
    public function adminAction(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user->role !== 'admin') {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized. Admin access required.'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'action' => 'required|string|in:resolve,dismiss',
            'deactivate_property' => 'nullable|boolean',
            'rejection_notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Valid action (resolve or dismiss) is required.'
            ], 422);
        }

        $report = PropertyReport::findOrFail($id);
        $action = $request->action;

        if ($action === 'resolve') {
            $report->status = 'resolved';
            $report->save();

            // Deactivate or Reject the reported property if requested
            if ($request->boolean('deactivate_property')) {
                $property = Property::find($report->property_id);
                if ($property) {
                    $property->verification_status = 'rejected';
                    $property->status = 'inactive';
                    $property->approval_notes = $request->rejection_notes ?? 'Deactivated due to reported violation/abuse.';
                    $property->save();

                    // Notify listing owner
                    $owner = User::find($property->user_id);
                    if ($owner) {
                        NotificationService::send(
                            $owner,
                            'Listing Deactivated due to Reports',
                            "Your listing '{$property->title}' has been deactivated and verification status set to Rejected following multiple user reports.",
                            'property_rejected',
                            ['property_id' => $property->id]
                        );
                    }
                }
            }
            
            $message = 'Report resolved successfully.';
        } else {
            $report->status = 'dismissed';
            $report->save();
            $message = 'Report dismissed successfully.';
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $report
        ]);
    }
}
