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
    // Authentication
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
    
    // Profile Management
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/profile', [ProfileController::class, 'show']);
        Route::put('/profile', [ProfileController::class, 'update']); // Legacy endpoint
        Route::put('/profile/info', [ProfileController::class, 'updateInfo']);
        Route::post('/profile/info', [ProfileController::class, 'updateInfo']); // For multipart/form-data (file upload)
        Route::put('/profile/password', [ProfileController::class, 'updatePassword']);
        
        // FCM Token Management
        Route::post('/user/fcm-token', [FcmTokenController::class, 'store']);
        Route::delete('/user/fcm-token/delete', [FcmTokenController::class, 'destroy']);
    });
    
    // Products
    Route::get('/products', [ProductController::class, 'index']);
    Route::get('/products/{id}', [ProductController::class, 'show']);
    
    // Cart & Checkout
    Route::post('/cart/validate', [CheckoutController::class, 'validateCart']);
    Route::get('/checkout/data', [CheckoutController::class, 'checkoutData'])->middleware('auth:sanctum');
    Route::get('/checkout/idempotency-key', [CheckoutController::class, 'generateIdempotencyKey'])->middleware('auth:sanctum');
    Route::post('/checkout/process', [CheckoutController::class, 'process'])
        ->middleware(['auth:sanctum', 'checkout.rate.limit']);
    
    // Orders History
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/orders/history', [CheckoutController::class, 'history']);
        Route::get('/orders/{transaction}', [CheckoutController::class, 'show']);
    });
    
    // Payment
    Route::post('/payment/notification', [PaymentController::class, 'notification']);
    Route::get('/payment/status/{orderId}', [PaymentController::class, 'checkStatus']);
    Route::get('/payment/finish', [PaymentController::class, 'finish']);
    
    // Discount
    Route::get('/discounts/available', [DiscountController::class, 'getAvailable']);
    Route::post('/discount/verify', [DiscountController::class, 'verifyCode']);

    // Mobile Password Reset
    Route::post('password/send-code', [MobilePasswordResetController::class, 'sendResetCode']);
    Route::post('password/verify-and-reset', [MobilePasswordResetController::class, 'verifyCodeAndReset']);

    // Favorite Menus
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/favorites', [FavoriteMenuController::class, 'index']);
        Route::post('/favorites', [FavoriteMenuController::class, 'store']);
        Route::delete('/favorites/{productId}', [FavoriteMenuController::class, 'destroy']);
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
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/announcements/unread/list', [ApiAnnouncementController::class, 'unread']);
        Route::post('/announcements/{id}/mark-as-read', [ApiAnnouncementController::class, 'markAsRead']);
        Route::post('/announcements/{id}/mark-as-unread', [ApiAnnouncementController::class, 'markAsUnread']);
        Route::post('/announcements/mark-all-as-read', [ApiAnnouncementController::class, 'markAllAsRead']);
    });
});