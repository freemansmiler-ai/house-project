<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\PropertyController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\UploadController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\VerificationController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\BlogCategoryController;
use App\Http\Controllers\BlogPostController;
use App\Http\Controllers\FaqController;
use App\Http\Controllers\ContactMessageController;
use App\Http\Controllers\SupportTicketController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/* Public Properties API */
Route::get('/properties', [PropertyController::class, 'index']);
Route::get('/properties/featured', [PropertyController::class, 'featured']);
Route::get('/properties/latest', [PropertyController::class, 'latest']);
Route::get('/properties/{id}', [PropertyController::class, 'show']);
Route::get('/cities/popular', [PropertyController::class, 'popularCities']);
Route::get('/agents/verified', [PropertyController::class, 'verifiedAgents']);
Route::get('/amenities', [PropertyController::class, 'allAmenities']);
Route::get('/reviews', [ReviewController::class, 'index']);

/* Public Blog API */
Route::get('/blog/categories', [BlogCategoryController::class, 'index']);
Route::get('/blog/posts', [BlogPostController::class, 'index']);
Route::get('/blog/posts/{slug}', [BlogPostController::class, 'show']);

/* Public FAQ API */
Route::get('/faqs', [FaqController::class, 'index']);

/* Public Contact Form API */
Route::post('/contact', [ContactMessageController::class, 'store'])->middleware('throttle:public-contact');

/* Public Auth Routes */
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:auth-login');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:auth-login');
Route::post('/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:auth-login');
Route::post('/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:auth-login');

/* Protected Auth Routes */
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::put('/profile', [AuthController::class, 'updateProfile']);
    Route::post('/verify-email', [AuthController::class, 'verifyEmail']);
    Route::post('/resend-email-verification', [AuthController::class, 'resendEmailVerification']);
    Route::post('/verify-phone', [AuthController::class, 'verifyPhone']);
    Route::post('/resend-phone-verification', [AuthController::class, 'resendPhoneVerification']);
    Route::get('/bookings', [BookingController::class, 'index']);
    Route::post('/bookings', [BookingController::class, 'store']);
    Route::put('/bookings/{id}/status', [BookingController::class, 'updateStatus']);
    Route::put('/bookings/{id}/reschedule', [BookingController::class, 'reschedule']);
    Route::post('/properties', [PropertyController::class, 'store']);
    Route::put('/properties/{id}', [PropertyController::class, 'update']);
    Route::delete('/properties/{id}', [PropertyController::class, 'destroy']);
    Route::post('/properties/report', [\App\Http\Controllers\PropertyReportController::class, 'store']);
    Route::post('/upload/images', [UploadController::class, 'uploadImages']);
    Route::get('/favorites', [FavoriteController::class, 'index']);
    Route::post('/favorites/toggle', [FavoriteController::class, 'toggle']);
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markAsRead']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    Route::post('/notifications/device-token', [NotificationController::class, 'deviceToken']);
    Route::get('/notifications/pushes', [NotificationController::class, 'pullPushes']);
    
    // Reviews API
    Route::post('/reviews', [ReviewController::class, 'store']);

    // Payment API
    Route::get('/payments', [\App\Http\Controllers\PaymentController::class, 'index']);
    Route::post('/payments', [\App\Http\Controllers\PaymentController::class, 'store']);

    // Subscription API
    Route::get('/subscriptions/active', [\App\Http\Controllers\SubscriptionController::class, 'active']);
    Route::post('/subscriptions/subscribe', [\App\Http\Controllers\SubscriptionController::class, 'subscribe']);

    Route::get('/verifications/status', [VerificationController::class, 'status']);
    Route::post('/verifications/submit', [VerificationController::class, 'submit']);

    // Messaging API
    Route::get('/messages/conversations', [\App\Http\Controllers\MessageController::class, 'conversations']);
    Route::get('/messages/{userId}', [\App\Http\Controllers\MessageController::class, 'history']);
    Route::post('/messages', [\App\Http\Controllers\MessageController::class, 'send']);
    Route::post('/messages/{receiverId}/typing', [\App\Http\Controllers\MessageController::class, 'setTyping']);

    // Support Tickets API
    Route::get('/tickets', [SupportTicketController::class, 'index']);
    Route::post('/tickets', [SupportTicketController::class, 'store'])->middleware('throttle:tickets-creation');
    Route::get('/tickets/{id}', [SupportTicketController::class, 'show']);
    Route::post('/tickets/{id}/reply', [SupportTicketController::class, 'reply'])->middleware('throttle:tickets-creation');
    Route::post('/tickets/{id}/close', [SupportTicketController::class, 'close']);

    /* Example Role-Based Protected Routes */
    Route::middleware('role:admin')->group(function () {
        Route::get('/admin/dashboard', [\App\Http\Controllers\AdminDashboardController::class, 'index']);
        Route::get('/admin/analytics', [\App\Http\Controllers\AdminDashboardController::class, 'analytics']);
        Route::get('/admin/verifications', [VerificationController::class, 'adminIndex']);
        Route::post('/admin/verifications/{id}/approve', [VerificationController::class, 'adminApprove']);
        Route::post('/admin/verifications/{id}/reject', [VerificationController::class, 'adminReject']);

        // User Management
        Route::get('/admin/users', [\App\Http\Controllers\AdminUserController::class, 'index']);
        Route::put('/admin/users/{id}/status', [\App\Http\Controllers\AdminUserController::class, 'updateStatus']);
        Route::put('/admin/users/{id}/role', [\App\Http\Controllers\AdminUserController::class, 'updateRole']);
        Route::put('/admin/users/{id}/verify', [\App\Http\Controllers\AdminUserController::class, 'verify']);
        Route::post('/admin/users/{id}/reset-password', [\App\Http\Controllers\AdminUserController::class, 'resetPassword']);
        Route::delete('/admin/users/{id}', [\App\Http\Controllers\AdminUserController::class, 'destroy']);

        // Property Approvals
        Route::get('/admin/properties/pending', [\App\Http\Controllers\AdminPropertyApprovalController::class, 'pendingList']);
        Route::post('/admin/properties/{id}/approve', [\App\Http\Controllers\AdminPropertyApprovalController::class, 'approve']);
        Route::post('/admin/properties/{id}/reject', [\App\Http\Controllers\AdminPropertyApprovalController::class, 'reject']);
        Route::post('/admin/properties/{id}/request-changes', [\App\Http\Controllers\AdminPropertyApprovalController::class, 'requestChanges']);
        Route::post('/admin/properties/{id}/under-review', [\App\Http\Controllers\AdminPropertyApprovalController::class, 'underReview']);

        // Property Abuse Reports
        Route::get('/admin/reports', [\App\Http\Controllers\PropertyReportController::class, 'adminIndex']);
        Route::post('/admin/reports/{id}/action', [\App\Http\Controllers\PropertyReportController::class, 'adminAction']);

        // Blog Management
        Route::post('/admin/blog/categories', [BlogCategoryController::class, 'store']);
        Route::put('/admin/blog/categories/{id}', [BlogCategoryController::class, 'update']);
        Route::delete('/admin/blog/categories/{id}', [BlogCategoryController::class, 'destroy']);
        Route::get('/admin/blog/posts', [BlogPostController::class, 'adminIndex']);
        Route::post('/admin/blog/posts', [BlogPostController::class, 'store']);
        Route::put('/admin/blog/posts/{id}', [BlogPostController::class, 'update']);
        Route::delete('/admin/blog/posts/{id}', [BlogPostController::class, 'destroy']);
        Route::post('/admin/blog/upload-image', [BlogPostController::class, 'uploadEditorImage']);

        // FAQ Management
        Route::post('/admin/faqs', [FaqController::class, 'store']);
        Route::put('/admin/faqs/{id}', [FaqController::class, 'update']);
        Route::delete('/admin/faqs/{id}', [FaqController::class, 'destroy']);

        // Contact Messages Management
        Route::get('/admin/contact-messages', [ContactMessageController::class, 'index']);
        Route::put('/admin/contact-messages/{id}/status', [ContactMessageController::class, 'updateStatus']);
        Route::delete('/admin/contact-messages/{id}', [ContactMessageController::class, 'destroy']);

        // Admin Support Tickets
        Route::get('/admin/tickets', [SupportTicketController::class, 'adminIndex']);
        Route::put('/admin/tickets/{id}/status', [SupportTicketController::class, 'adminUpdateStatus']);
        Route::post('/admin/tickets/{id}/reply', [SupportTicketController::class, 'reply'])->middleware('throttle:tickets-creation');
    });

    Route::middleware('role:landlord')->group(function () {
        Route::get('/landlord/dashboard', function () {
            return response()->json(['message' => 'Welcome to the landlord dashboard!']);
        });
    });

    Route::middleware('role:agent')->group(function () {
        Route::get('/agent/dashboard', function () {
            return response()->json(['message' => 'Welcome to the agent dashboard!']);
        });
    });

    Route::middleware('role:tenant')->group(function () {
        Route::get('/tenant/dashboard', function () {
            return response()->json(['message' => 'Welcome to the tenant portal!']);
        });
    });
});
