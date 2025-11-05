<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Transaction;

class ProductAnalyticsController extends Controller
{
    public function getTopProducts()
    {
        // Get all completed transactions (only select needed columns)
        $completedTransactions = Transaction::select('id', 'items')
            ->where('status', 'completed')
            ->get();

        // Process transactions to calculate total sales for each product
        $productSales = [];
        foreach ($completedTransactions as $transaction) {
            // Ensure items is an array
            $items = is_array($transaction->items) ? $transaction->items : [];
            foreach ($items as $item) {
                // Support common keys: id/product_id and quantity/qty
                $productId = $item['id'] ?? $item['product_id'] ?? null;
                $quantity = $item['quantity'] ?? $item['qty'] ?? null;
                if ($productId === null || $quantity === null) {
                    continue;
                }

                if (!isset($productSales[$productId])) {
                    $productSales[$productId] = 0;
                }
                $productSales[$productId] += (int) $quantity;
            }
        }

        // Sort products by sales and get top 6
        arsort($productSales);
        $topProductIds = array_keys(array_slice($productSales, 0, 6, true));

        // Get top sold product details (only select needed columns)
        $topSoldProducts = Product::select('id', 'nama', 'harga', 'gambar')
            ->whereIn('id', $topProductIds)
            ->get()
            ->map(function ($product) use ($productSales) {
                $product->total_sold = $productSales[$product->id] ?? 0;
                return $product;
            })
            ->filter(function ($product) {
                return $product->total_sold > 0; // Only show products with sales > 0
            })
            ->sortByDesc('total_sold')->values();

        // Get 6 most favorited products without GROUP BY issues (uses subquery)
        $topFavoritedProducts = Product::select('id', 'nama', 'harga', 'gambar')
            ->withCount(['favoriteMenus as favorite_count'])
            ->having('favorite_count', '>', 0) // Only get products with favorites > 0
            ->orderBy('favorite_count', 'desc')
            ->take(6)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'top_sold_products' => $topSoldProducts->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->nama,
                        'price' => $product->harga,
                        'image' => $product->gambar ? asset('storage/' . $product->gambar) : null,
                        'total_sold' => $product->total_sold
                    ];
                }),
                'top_favorited_products' => $topFavoritedProducts->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->nama,
                        'price' => $product->harga,
                        'image' => $product->gambar ? asset('storage/' . $product->gambar) : null,
                        'favorite_count' => $product->favorite_count
                    ];
                })
            ]
        ]);
    }
}