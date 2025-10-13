<?php

use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use Illuminate\Foundation\Application;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Kasir\ProductController;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {

    Route::middleware(['role:owner'])->group(function () {
        Route::get('/owner/dashboard', [DashboardController::class, 'owner'])->name('owner.dashboard');
    });

    Route::middleware(['role:kasir'])->group(function () {
        Route::get('/kasir/dashboard', [DashboardController::class, 'kasir'])->name('kasir.dashboard');
        Route::resource('kasir/products', ProductController::class)->name('products.index', 'products.store', 'products.update', 'products.destroy');
    });

    Route::middleware(['role:karyawan'])->group(function () {
        Route::get('/karyawan/dashboard', [DashboardController::class, 'karyawan'])->name('karyawan.dashboard');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
