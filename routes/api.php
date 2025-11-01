<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\ProfileController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\CheckoutController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\DiscountController;
use App\Http\Controllers\Api\V1\SettingController;
use App\Http\Controllers\Api\V1\ProductAnalyticsController;
use App\Http\Controllers\Api\V1\MobilePasswordResetController;
use App\Http\Controllers\Api\V1\FcmTokenController;
use App\Http\Controllers\Api\V1\AnnouncementController as ApiAnnouncementController;
use App\Http\Controllers\FavoriteMenuController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| SmartOrder Mobile API v1
|
*/

// Authentication Endpoints
Route::prefix('v1')->group(function () {
    // Authentication - Rate limited to prevent brute force attacks
    Route::post('/register', [AuthController::class, 'register'])
        ->middleware('rate.limit:auth.register');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('rate.limit:auth.login');
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    
    // Device Management
    Route::middleware(['auth:sanctum', 'device.token'])->group(function () {
        Route::get('/devices', [AuthController::class, 'getDevices']);
        Route::post('/devices/revoke-others', [AuthController::class, 'revokeOtherDevices']);
    });
    
    // Profile Management
    Route::middleware(['auth:sanctum', 'device.token'])->group(function () {
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']); // Legacy endpoint
        Route::put('/profile/info', [ProfileController::class, 'updateInfo'])
            ->middleware('rate.limit:write.intensive');
        Route::post('/profile/info', [ProfileController::class, 'updateInfo']) // For multipart/form-data (file upload)
            ->middleware('rate.limit:write.intensive');
        Route::put('/profile/password', [ProfileController::class, 'updatePassword'])
            ->middleware('rate.limit:auth.password.reset');
        
        // FCM Token Management
        Route::post('/user/fcm-token', [FcmTokenController::class, 'store'])
            ->middleware('rate.limit:write.intensive');
        Route::delete('/user/fcm-token/delete', [FcmTokenController::class, 'destroy']);
    });
    
    // Products
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    
    // Cart & Checkout
    Route::post('/cart/validate', [CheckoutController::class, 'validateCart'])
        ->middleware('rate.limit:write.intensive');
    Route::get('/checkout/data', [CheckoutController::class, 'checkoutData'])->middleware(['auth:sanctum', 'device.token']);
    Route::get('/checkout/idempotency-key', [CheckoutController::class, 'generateIdempotencyKey'])->middleware(['auth:sanctum', 'device.token']);
    Route::post('/checkout/process', [CheckoutController::class, 'process'])
        ->middleware(['auth:sanctum', 'device.token', 'checkout.rate.limit', 'rate.limit:payment.checkout']);
    
    // Orders History
    Route::middleware(['auth:sanctum', 'device.token'])->group(function () {
        Route::get('/orders/history', [CheckoutController::class, 'history']);
        Route::get('/orders/{transaction}', [CheckoutController::class, 'show']);
    });
    
    // Payment
    Route::post('/payment/notification', [PaymentController::class, 'notification'])
        ->middleware('rate.limit:payment.webhook');
    Route::get('/payment/status/{orderId}', [PaymentController::class, 'checkStatus'])
        ->middleware('rate.limit:read.heavy');
    Route::get('/payment/finish', [PaymentController::class, 'finish']);
    
    // Discount
    Route::get('/discounts/available', [DiscountController::class, 'getAvailable']);
    Route::post('/discount/verify', [DiscountController::class, 'verifyCode'])
        ->middleware('rate.limit:discount.verify');

    // Mobile Password Reset - Rate limited to prevent abuse
    Route::post('password/send-code', [MobilePasswordResetController::class, 'sendResetCode'])
        ->middleware('rate.limit:auth.password.reset');
    Route::post('password/verify-and-reset', [MobilePasswordResetController::class, 'verifyCodeAndReset'])
        ->middleware('rate.limit:auth.password.reset');

    // Favorite Menus
    Route::middleware(['auth:sanctum', 'device.token'])->group(function () {
        Route::get('/favorites', [FavoriteMenuController::class, 'index']);
        Route::post('/favorites', [FavoriteMenuController::class, 'store'])
            ->middleware('rate.limit:write.intensive');
        Route::delete('/favorites/{productId}', [FavoriteMenuController::class, 'destroy'])
            ->middleware('rate.limit:write.intensive');
        Route::get('/favorites/check/{productId}', [FavoriteMenuController::class, 'checkFavorite']);
    });

    // Product Analytics
    Route::get('/products/analytics/top', [ProductAnalyticsController::class, 'getTopProducts']);

    // Settings
    Route::get('/settings', [SettingController::class, 'getStoreSettings']);

    // Announcements (public)
    Route::get('/announcements', [ApiAnnouncementController::class, 'index']);
    Route::get('/announcements/latest', [ApiAnnouncementController::class, 'latest']);
    Route::get('/announcements/count', [ApiAnnouncementController::class, 'count']);
    Route::get('/announcements/{id}', [ApiAnnouncementController::class, 'show']);

    // Announcements (authenticated customers only)
    Route::middleware(['auth:sanctum', 'device.token'])->group(function () {
        Route::get('/announcements/unread/list', [ApiAnnouncementController::class, 'unread']);
        Route::post('/announcements/{id}/mark-as-read', [ApiAnnouncementController::class, 'markAsRead'])
            ->middleware('rate.limit:write.intensive');
        Route::post('/announcements/{id}/mark-as-unread', [ApiAnnouncementController::class, 'markAsUnread'])
            ->middleware('rate.limit:write.intensive');
        Route::post('/announcements/mark-all-as-read', [ApiAnnouncementController::class, 'markAllAsRead'])
            ->middleware('rate.limit:write.intensive');
    });
});