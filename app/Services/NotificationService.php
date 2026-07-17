<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Mail\SystemNotificationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class NotificationService
{
    /**
     * Dispatch notification across all channels.
     */
    public static function send(User $user, string $title, string $message, string $type, array $extraData = []): void
    {
        // Channel 1: In-App Notification
        try {
            Notification::create([
                'user_id' => $user->id,
                'title' => $title,
                'message' => $message,
                'type' => $type,
                'read_at' => null
            ]);
        } catch (\Exception $e) {
            Log::error("Failed to create in-app notification: " . $e->getMessage());
        }

        // Channel 2: Email Notification
        try {
            Mail::to($user->email)->send(new SystemNotificationMail($title, $message, $type));
            Log::info("Email notification successfully sent/queued to {$user->email}.");
        } catch (\Exception $e) {
            Log::warning("Email channel failed or skipped (non-configured SMTP). Logging instead: [{$title}] {$message}. Details: " . $e->getMessage());
        }

        // Channel 3: Push Notification
        // Since we are running locally, we emulate Push notification delivery.
        // We log the push and store it in a Cache store. The frontend can query or poll
        // /api/notifications/pushes to trigger native browser push notifications immediately!
        try {
            $pushPayload = [
                'id' => uniqid(),
                'user_id' => $user->id,
                'title' => $title,
                'body' => $message,
                'type' => $type,
                'extra' => $extraData,
                'timestamp' => now()->toIso8601String()
            ];

            // Push token lookup emulation
            if ($user->device_token) {
                Log::info("Push Notification dispatched to device ID {$user->device_token}: " . json_encode($pushPayload));
            } else {
                Log::info("Simulated Push Notification dispatched to unregistered user #{$user->id}: " . json_encode($pushPayload));
            }

            // Append to User's pending push cache
            $cacheKey = "pending_pushes:{$user->id}";
            $existingPushes = Cache::get($cacheKey, []);
            $existingPushes[] = $pushPayload;
            Cache::put($cacheKey, $existingPushes, now()->addMinutes(10));

        } catch (\Exception $e) {
            Log::error("Push notification emulation failed: " . $e->getMessage());
        }
    }
}
