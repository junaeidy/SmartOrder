<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ProductResource;
use App\Models\Product;
use App\Models\Setting;
use App\Models\FavoriteMenu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class ProductController extends Controller
{
    /**
     * Get all available products.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function index()
    {
        // Cache store status for 5 minutes
        $isStoreOpen = Cache::remember('store_is_open', 300, function () {
            return $this->isStoreOpen();
        });
        
        $storeHours = Cache::remember('store_hours', 300, function () {
            return $this->getStoreHours();
        });
        
        // Get customer to optimize favorite checking
        $customer = Auth::guard('customer')->user();
        
        // Cache products for 10 minutes (or use cache tags to invalidate on updates)
        $cacheKey = $customer ? "products_customer_{$customer->id}" : 'products_guest';
        
        $products = Cache::remember($cacheKey, 600, function () use ($customer) {
            if ($customer) {
                // Load products with customer's favorites to avoid N+1 queries
                return Product::with(['favoriteMenus' => function($query) use ($customer) {
                    $query->where('customer_id', $customer->id);
                }])->get();
            } else {
                return Product::all();
            }
        });
        
        return response()->json([
            'success' => true,
            'data' => [
                'isStoreOpen' => $isStoreOpen,
                'storeHours' => $storeHours,
                'products' => ProductResource::collection($products)
            ]
        ]);
    }

    /**
     * Get single product details.
     *
     * @param int $id
     * @return \Illuminate\Http\JsonResponse
     */
    public function show($id)
    {
        $product = Product::find($id);
        
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }
        
        return response()->json([
            'success' => true,
            'data' => [
                'product' => new ProductResource($product)
            ]
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
