<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index()
    {
        // Check if store is open
        $isStoreOpen = $this->isStoreOpen();
        $storeHours = $this->getStoreHours();
        
        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'isStoreOpen' => $isStoreOpen,
            'storeHours' => $storeHours,
            'products' => Product::all()->map(function($product) {
                return [
                    'id' => $product->id,
                    'nama' => $product->nama,
                    'harga' => $product->harga,
                    'stok' => $product->stok,
                    'closed' => (bool)($product->closed ?? false),
                    'gambar' => $product->gambar ? asset('storage/' . $product->gambar) : null,
                ];
            })
        ]);
    }
    
    /**
     * Check if the store is currently open.
     *
     * @return bool
     */
    private function isStoreOpen()
    {
        // If store is manually closed, return false
        if (\App\Models\Setting::get('store_closed', false)) {
            return false;
        }
        
        // Get current day and time
        $now = now();
        $currentDay = strtolower($now->format('l')); // monday, tuesday, etc.
        $currentTime = $now->format('H:i');
        
        // Get store hours for current day
        $openTime = \App\Models\Setting::get($currentDay . '_open', '08:00');
        $closeTime = \App\Models\Setting::get($currentDay . '_close', '20:00');
        
        // Check if current time is within store hours
        return $currentTime >= $openTime && $currentTime <= $closeTime;
    }
    
    /**
     * Get the store hours for displaying to the customer.
     *
     * @return array
     */
    private function getStoreHours()
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $hours = [];
        
        foreach ($days as $day) {
            $hours[$day] = [
                'open' => \App\Models\Setting::get($day . '_open', '08:00'),
                'close' => \App\Models\Setting::get($day . '_close', '20:00'),
            ];
        }
        
        return $hours;
    }
}