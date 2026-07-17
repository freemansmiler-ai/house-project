<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Agent;
use App\Models\Landlord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use App\Traits\LogsActivity;

class AdminUserController extends Controller
{
    use LogsActivity;

    /**
     * List all users.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['profile', 'landlord', 'agent']);

        // Search filter
        if ($request->has('search') && !empty($request->search)) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        // Role filter
        if ($request->has('role') && !empty($request->role) && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        // Status filter
        if ($request->has('status') && !empty($request->status) && $request->status !== 'all') {
            $query->where('status', $request->status);
        }

        $users = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Update user status (e.g. suspend or activate).
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:active,suspended,inactive'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid status option.'
            ], 422);
        }

        $oldStatus = $user->status;
        $user->status = $request->status;
        $user->save();

        $this->logActivity('admin_user_status_update', "Admin updated status of user ID: {$id} ({$user->email}) from '{$oldStatus}' to '{$request->status}'");

        return response()->json([
            'success' => true,
            'message' => "User status updated to {$request->status} successfully.",
            'data' => $user
        ]);
    }

    /**
     * Update user role.
     */
    public function updateRole(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'role' => 'required|string|in:admin,landlord,agent,tenant'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role specified.'
            ], 422);
        }

        $oldRole = $user->role;
        $user->role = $request->role;
        $user->save();

        // Initialize landlord/agent profile if missing
        if ($request->role === 'landlord' && !$user->landlord) {
            Landlord::create([
                'user_id' => $user->id,
                'is_verified' => false
            ]);
        } elseif ($request->role === 'agent' && !$user->agent) {
            Agent::create([
                'user_id' => $user->id,
                'is_verified' => false
            ]);
        }

        $this->logActivity('admin_user_role_update', "Admin updated role of user ID: {$id} ({$user->email}) from '{$oldRole}' to '{$request->role}'");

        return response()->json([
            'success' => true,
            'message' => "User role updated to {$request->role} successfully.",
            'data' => $user->load(['landlord', 'agent'])
        ]);
    }

    /**
     * Direct toggle user verification status (Approve user).
     */
    public function verify(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'is_verified' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Verification state must be boolean.'
            ], 422);
        }

        $verify = $request->is_verified;

        if ($user->role === 'landlord') {
            if (!$user->landlord) {
                Landlord::create(['user_id' => $user->id, 'is_verified' => $verify]);
            } else {
                $user->landlord->is_verified = $verify;
                $user->landlord->save();
            }
        } elseif ($user->role === 'agent') {
            if (!$user->agent) {
                Agent::create(['user_id' => $user->id, 'is_verified' => $verify]);
            } else {
                $user->agent->is_verified = $verify;
                $user->agent->save();
            }
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Only landlords and agents can hold verification status.'
            ], 400);
        }

        $stateStr = $verify ? 'verified' : 'unverified';
        $this->logActivity('admin_user_verification_toggle', "Admin toggled verification of user ID: {$id} ({$user->email}) to '{$stateStr}'");

        return response()->json([
            'success' => true,
            'message' => $verify ? 'User verified successfully.' : 'User verification revoked.',
            'data' => $user->load(['landlord', 'agent'])
        ]);
    }

    /**
     * Reset user password manually.
     */
    public function resetPassword(Request $request, $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $validator = Validator::make($request->all(), [
            'password' => 'required|string|min:8'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors(),
                'message' => 'Password must be at least 8 characters.'
            ], 422);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        $this->logActivity('admin_user_password_reset', "Admin manually reset password for user ID: {$id} ({$user->email})");

        return response()->json([
            'success' => true,
            'message' => 'User password reset successfully.'
        ]);
    }

    /**
     * Delete user completely.
     */
    public function destroy($id): JsonResponse
    {
        $user = User::findOrFail($id);

        $email = $user->email;
        $name = $user->name;

        // Delete user listings, profiles, etc. are handled by cascades or manual deletion
        $user->delete();

        $this->logActivity('admin_user_delete', "Admin deleted user ID: {$id} (Name: {$name}, Email: {$email})");

        return response()->json([
            'success' => true,
            'message' => 'User account deleted successfully.'
        ]);
    }
}
