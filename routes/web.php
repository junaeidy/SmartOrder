<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Kasir\ProductController;
use App\Http\Controllers\CheckoutController;
use App\Models\QueueCounter;
use Carbon\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Broadcast;

// Redirect root to login
Route::get('/', function () {
    return redirect()->route('login');
});

// Midtrans Routes
Route::post('/midtrans/notification', [App\Http\Controllers\MidtransController::class, 'notification'])
    ->name('midtrans.notification');
    
Route::get('/midtrans/status/{orderId}', [App\Http\Controllers\MidtransController::class, 'checkStatus'])
    ->name('midtrans.check-status');
    
Route::get('/midtrans/finish/{orderId?}', [App\Http\Controllers\MidtransController::class, 'finish'])
    ->name('midtrans.finish');
    
// Discount Code Verification
Route::post('/discount/verify', [App\Http\Controllers\DiscountController::class, 'verifyCode'])
    ->name('discount.verify');

Route::prefix('api')->group(function () {
    Route::get('/payment/check-status/{orderId}', [App\Http\Controllers\MidtransController::class, 'checkStatus'])
        ->name('payment.check.status');
});

// Admin route to manually reset the queue counter (protected by admin middleware in production)
Route::get('/admin/reset-queue', function() {
    Artisan::call('queue:reset-counter');
    return redirect()->back()->with('message', 'Queue counter has been reset successfully.');
});

Route::middleware(['auth', 'verified'])->group(function () {

    Route::middleware(['role:kasir'])->group(function () {
        Route::get('/kasir/dashboard', [DashboardController::class, 'kasir'])->name('kasir.dashboard');
        Route::resource('kasir/products', ProductController::class)->name('products.index', 'products.store', 'products.update', 'products.destroy');
        Route::put('/kasir/products/{product}/toggle-closed', [ProductController::class, 'toggleClosed'])->name('products.toggleClosed');
        Route::get('/kasir/stock/alerts', [ProductController::class, 'alerts'])->name('kasir.stock.alerts');
        // Reports
        Route::get('/kasir/reports', [App\Http\Controllers\ReportsController::class, 'index'])->name('kasir.reports');
        Route::get('/kasir/reports/export/excel', [App\Http\Controllers\ReportsController::class, 'exportExcel'])->name('kasir.reports.export.excel');
        Route::get('/kasir/reports/export/pdf', [App\Http\Controllers\ReportsController::class, 'exportPdf'])->name('kasir.reports.export.pdf');
        // Transaksi (confirmation)
        Route::get('/kasir/transaksi', [App\Http\Controllers\KasirTransactionController::class, 'index'])->name('kasir.transaksi');
        Route::put('/kasir/transaksi/{transaction}/confirm', [App\Http\Controllers\KasirTransactionController::class, 'confirm'])->name('kasir.transaksi.confirm');
        Route::put('/kasir/transaksi/{transaction}/cancel', [App\Http\Controllers\KasirTransactionController::class, 'cancel'])->name('kasir.transaksi.cancel');

        // Settings
        Route::get('/kasir/settings', [App\Http\Controllers\Admin\SettingsController::class, 'index'])->name('admin.settings');
        Route::post('/kasir/settings/store-hours', [App\Http\Controllers\Admin\SettingsController::class, 'updateStoreHours'])->name('admin.settings.store-hours');
        Route::post('/kasir/settings/store-settings', [App\Http\Controllers\Admin\SettingsController::class, 'updateStoreSettings'])->name('admin.settings.store-settings');
        
        // Discounts
        Route::post('/kasir/discounts', [App\Http\Controllers\Admin\DiscountController::class, 'store'])->name('admin.discounts.store');
        Route::put('/kasir/discounts/{discount}', [App\Http\Controllers\Admin\DiscountController::class, 'update'])->name('admin.discounts.update');
        Route::delete('/kasir/discounts/{discount}', [App\Http\Controllers\Admin\DiscountController::class, 'destroy'])->name('admin.discounts.destroy');
        Route::put('/kasir/discounts/{discount}/toggle', [App\Http\Controllers\Admin\DiscountController::class, 'toggleActive'])->name('admin.discounts.toggle');
        
        // Announcements
        Route::get('/kasir/announcements', [App\Http\Controllers\AnnouncementController::class, 'index'])->name('kasir.announcements');
        Route::post('/kasir/announcements', [App\Http\Controllers\AnnouncementController::class, 'store'])->name('kasir.announcements.store');
        Route::post('/kasir/announcements/{announcement}/send', [App\Http\Controllers\AnnouncementController::class, 'send'])->name('kasir.announcements.send');
        Route::delete('/kasir/announcements/{announcement}', [App\Http\Controllers\AnnouncementController::class, 'destroy'])->name('kasir.announcements.destroy');
    });

    Route::middleware(['role:karyawan'])->group(function () {
        Route::get('/karyawan/dashboard', [DashboardController::class, 'karyawan'])->name('karyawan.dashboard');
        Route::get('/karyawan/orders', [App\Http\Controllers\KaryawanOrderController::class, 'index'])->name('karyawan.orders');
        Route::put('/karyawan/orders/{transaction}', [App\Http\Controllers\KaryawanOrderController::class, 'processOrder'])->name('karyawan.orders.process');
    });
});

// Public broadcasting channel registration (if needed for authorization)
Broadcast::channel('products', function ($user = null) {
    return true;
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
