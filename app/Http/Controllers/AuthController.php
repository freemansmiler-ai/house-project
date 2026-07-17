<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Profile;
use App\Models\Landlord;
use App\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Traits\LogsActivity;

class AuthController extends Controller
{
    use LogsActivity;

    /**
     * Register a new user (tenant, landlord, or agent).
     */
    public function register(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'required|string|max:20|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:tenant,landlord,agent',
            // Additional details depending on role
            'company_name' => 'nullable|required_if:role,landlord|string|max:255',
            'agency_name' => 'nullable|required_if:role,agent|string|max:255',
            'license_number' => 'nullable|required_if:role,agent|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Generate OTPs
        $emailOtp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $phoneOtp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'status' => 'active',
            'email_verification_code' => $emailOtp,
            'phone_verification_code' => $phoneOtp,
        ]);

        // Create Profile record
        Profile::create([
            'user_id' => $user->id,
            'whatsapp_number' => $request->phone,
        ]);

        // Create Role-specific records
        if ($user->role === 'landlord') {
            Landlord::create([
                'user_id' => $user->id,
                'company_name' => $request->company_name,
            ]);
        } elseif ($user->role === 'agent') {
            Agent::create([
                'user_id' => $user->id,
                'agency_name' => $request->agency_name,
                'license_number' => $request->license_number,
            ]);
        }

        // Log OTP codes for simulator / review
        Log::info("OTP Codes for User ID {$user->id} ({$user->email}): Email OTP = {$emailOtp}, Phone OTP = {$phoneOtp}");

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->logActivity('register', "User registered account with email: {$user->email} and role: {$user->role}", $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Registration successful! Verification OTPs have been sent.',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'data' => $user->load(['profile', 'landlord', 'agent']),
            // In dev mode, return OTPs directly so frontend can autofill
            'development_otps' => [
                'email_otp' => $emailOtp,
                'phone_otp' => $phoneOtp
            ]
        ], 201);
    }

    /**
     * Authenticate user and return token.
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('email', $request->email)->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            $this->logActivity('login_failed', "Failed login attempt for email: {$request->email}");
            return response()->json([
                'success' => false,
                'message' => 'Invalid credentials'
            ], 411);
        }

        if ($user->status === 'suspended') {
            $this->logActivity('login_blocked', "Blocked login attempt to suspended account: {$user->email}", $user->id);
            return response()->json([
                'success' => false,
                'message' => 'Your account has been suspended. Please contact support.'
            ], 403);
        }

        $token = $user->createToken('auth_token')->plainTextToken;

        $this->logActivity('login', "User {$user->email} logged in successfully.", $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'access_token' => $token,
            'token_type' => 'Bearer',
            'data' => $user->load(['profile', 'landlord', 'agent']),
            'development_otps' => [
                'email_otp' => $user->email_verification_code,
                'phone_otp' => $user->phone_verification_code
            ]
        ]);
    }

    /**
     * Terminate the user token (Logout).
     */
    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        $user->currentAccessToken()->delete();

        $this->logActivity('logout', "User {$user->email} logged out successfully.", $user->id);

        return response()->json([
            'success' => true,
            'message' => 'Logged out successfully'
        ]);
    }

    /**
     * Retrieve authenticated user details.
     */
    public function user(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $request->user()->load(['profile', 'landlord', 'agent'])
        ]);
    }

    /**
     * Verify email with OTP code.
     */
    public function verifyEmail(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if ($user->email_verification_code !== $request->code) {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect verification code.'
            ], 400);
        }

        $user->email_verified_at = now();
        $user->email_verification_code = null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Email address verified successfully!',
            'data' => $user
        ]);
    }

    /**
     * Resend Email OTP.
     */
    public function resendEmailVerification(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->email_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Email already verified.'
            ], 400);
        }

        $emailOtp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $user->email_verification_code = $emailOtp;
        $user->save();

        Log::info("Resent Email OTP for User ID {$user->id} ({$user->email}): OTP = {$emailOtp}");

        return response()->json([
            'success' => true,
            'message' => 'Verification code resent successfully.',
            'development_otps' => [
                'email_otp' => $emailOtp
            ]
        ]);
    }

    /**
     * Verify phone with OTP code.
     */
    public function verifyPhone(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        if ($user->phone_verification_code !== $request->code) {
            return response()->json([
                'success' => false,
                'message' => 'Incorrect verification code.'
            ], 400);
        }

        $user->phone_verified_at = now();
        $user->phone_verification_code = null;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Phone number verified successfully!',
            'data' => $user
        ]);
    }

    /**
     * Resend Phone OTP.
     */
    public function resendPhoneVerification(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user->phone_verified_at) {
            return response()->json([
                'success' => false,
                'message' => 'Phone number already verified.'
            ], 400);
        }

        $phoneOtp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        $user->phone_verification_code = $phoneOtp;
        $user->save();

        Log::info("Resent Phone OTP for User ID {$user->id} ({$user->phone}): OTP = {$phoneOtp}");

        return response()->json([
            'success' => true,
            'message' => 'Verification code resent successfully.',
            'development_otps' => [
                'phone_otp' => $phoneOtp
            ]
        ]);
    }

    /**
     * Send password reset request (Forgot Password).
     */
    public function forgotPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        // Generate simple reset OTP and store it in password_reset_tokens table
        $otp = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        
        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => Hash::make($otp),
                'created_at' => now()
            ]
        );

        Log::info("Password Reset OTP for {$request->email}: OTP = {$otp}");

        return response()->json([
            'success' => true,
            'message' => 'A password reset code has been sent to your email address.',
            'development_otp' => $otp
        ]);
    }

    /**
     * Reset password using OTP code.
     */
    public function resetPassword(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|exists:users,email',
            'code' => 'required|string|size:6',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $reset = \DB::table('password_reset_tokens')->where('email', $request->email)->first();

        if (!$reset || !Hash::check($request->code, $reset->token)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired password reset code.'
            ], 400);
        }

        // Update User Password
        $user = User::where('email', $request->email)->first();
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete reset token
        \DB::table('password_reset_tokens')->where('email', $request->email)->delete();

        return response()->json([
            'success' => true,
            'message' => 'Your password has been successfully reset. You can now login.'
        ]);
    }

    /**
     * Update user profile settings.
     */
    public function updateProfile(Request $request): JsonResponse
    {
        $user = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'whatsapp_number' => 'nullable|string|max:20',
            'bio' => 'nullable|string|max:1000',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:100',
            'region' => 'nullable|string|max:100',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $user->name = $request->name;
        if ($request->has('phone')) {
            $user->phone = $request->phone;
        }
        $user->save();

        $user->profile()->updateOrCreate(
            ['user_id' => $user->id],
            $request->only(['first_name', 'last_name', 'whatsapp_number', 'bio', 'address', 'city', 'region'])
        );

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully!',
            'data' => $user->load(['profile', 'landlord', 'agent'])
        ]);
    }
}
