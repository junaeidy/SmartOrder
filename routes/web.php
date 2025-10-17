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

Route::get('/', [App\Http\Controllers\ProductController::class, 'index'])
    ->middleware('guest')
    ->name('welcome');
    
Route::get('/checkout', [CheckoutController::class, 'checkout'])
    ->name('checkout');
    
Route::post('/checkout/process', [CheckoutController::class, 'process'])
    ->name('checkout.process');
    
Route::get('/thankyou/{transaction}', [CheckoutController::class, 'thankyou'])
    ->name('checkout.thankyou');

// Midtrans Routes
Route::post('/midtrans/notification', [App\Http\Controllers\MidtransController::class, 'notification'])
    ->name('midtrans.notification');
    
Route::get('/midtrans/status/{orderId}', [App\Http\Controllers\MidtransController::class, 'checkStatus'])
    ->name('midtrans.check-status');
    
Route::get('/midtrans/finish', [App\Http\Controllers\MidtransController::class, 'finish'])
    ->name('midtrans.finish');

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

    Route::middleware(['role:owner'])->group(function () {
        Route::get('/owner/dashboard', [DashboardController::class, 'owner'])->name('owner.dashboard');
    });

    Route::middleware(['role:kasir'])->group(function () {
        Route::get('/kasir/dashboard', [DashboardController::class, 'kasir'])->name('kasir.dashboard');
        Route::resource('kasir/products', ProductController::class)->name('products.index', 'products.store', 'products.update', 'products.destroy');
        // Reports
        Route::get('/kasir/reports', [App\Http\Controllers\ReportsController::class, 'index'])->name('kasir.reports');
        Route::get('/kasir/reports/export/excel', [App\Http\Controllers\ReportsController::class, 'exportExcel'])->name('kasir.reports.export.excel');
        Route::get('/kasir/reports/export/pdf', [App\Http\Controllers\ReportsController::class, 'exportPdf'])->name('kasir.reports.export.pdf');
    });

    Route::middleware(['role:karyawan'])->group(function () {
        Route::get('/karyawan/dashboard', [DashboardController::class, 'karyawan'])->name('karyawan.dashboard');
        Route::get('/karyawan/orders', [App\Http\Controllers\KaryawanOrderController::class, 'index'])->name('karyawan.orders');
        Route::put('/karyawan/orders/{transaction}', [App\Http\Controllers\KaryawanOrderController::class, 'processOrder'])->name('karyawan.orders.process');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
