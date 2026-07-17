<?php

namespace App\Traits;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

trait LogsActivity
{
    /**
     * Log a user activity in the audit_logs table.
     *
     * @param string $action       e.g., 'login', 'update_user_role', 'approve_property'
     * @param string|null $description Details about the action
     * @param int|null $userId     Override user ID (useful for login actions before session starts)
     */
    protected function logActivity(string $action, ?string $description = null, ?int $userId = null): void
    {
        try {
            $user = Auth::user();
            $request = request();

            AuditLog::create([
                'user_id' => $userId ?: ($user ? $user->id : null),
                'action' => $action,
                'description' => $description,
                'ip_address' => $request->ip(),
                'user_agent' => $request->header('User-Agent'),
            ]);
        } catch (\Exception $e) {
            // Log writing failure to laravel error logs to avoid breaking user actions if database write fails
            Log::error("Failed to write audit log for action '{$action}': " . $e->getMessage());
        }
    }
}
