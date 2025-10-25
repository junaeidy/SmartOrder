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
        Route::put('/profile', [ProfileController::class, 'update']);
    });
    
    // Products
    Route::get('/products', [ProductController::class, 'index']);
    
    // Cart & Checkout
    Route::post('/cart/validate', [CheckoutController::class, 'validateCart']);
    Route::get('/checkout/data', [CheckoutController::class, 'checkoutData'])->middleware('auth:sanctum');
    Route::post('/checkout/process', [CheckoutController::class, 'process'])->middleware('auth:sanctum');
    
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
    Route::post('/discount/verify', [DiscountController::class, 'verifyCode']);

    // Settings
    Route::get('/settings', [SettingController::class, 'getStoreSettings']);
});