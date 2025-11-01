<?php

namespace App\Http\Controllers;

use App\Models\FavoriteMenu;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class FavoriteMenuController extends Controller
{
    /**
     * Display a listing of customer's favorite menus.
     */
    public function index()
    {
        try {
            $customer = Auth::guard('customer')->user();
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pelanggan tidak diautentikasi'
                ], 401);
            }

            $favorites = FavoriteMenu::where('customer_id', $customer->id)
                ->with(['product' => function($query) {
                    $query->select('id', 'nama', 'harga', 'gambar', 'stok', 'closed');
                }])
                ->get();

            $favoriteProducts = $favorites->map(function($favorite) {
                return [
                    'id' => $favorite->product->id,
                    'nama' => $favorite->product->nama,
                    'harga' => $favorite->product->harga,
                    'gambar' => $favorite->product->gambar,
                    'stok' => $favorite->product->stok,
                    'closed' => $favorite->product->closed,
                    'added_to_favorites_at' => $favorite->created_at->format('Y-m-d H:i:s')
                ];
            });

            return response()->json([
                'success' => true,
                'message' => 'Menu favorit berhasil diambil',
                'data' => $favoriteProducts
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal mengambil menu favorit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Store a newly created favorite menu in storage.
     */
    public function store(Request $request)
    {
        try {
            $customer = Auth::guard('customer')->user();
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pelanggan tidak diautentikasi'
                ], 401);
            }

            $request->validate([
                'product_id' => 'required|integer|exists:products,id'
            ]);

            // Check if product is already in favorites
            $existingFavorite = FavoriteMenu::where('customer_id', $customer->id)
                ->where('product_id', $request->product_id)
                ->first();

            if ($existingFavorite) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produk sudah ada di favorit Anda'
                ], 409);
            }

            // Get product details
            $product = Product::find($request->product_id);

            // Create favorite
            $favorite = FavoriteMenu::create([
                'customer_id' => $customer->id,
                'product_id' => $request->product_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil ditambahkan ke favorit',
                'data' => [
                    'favorite_id' => $favorite->id,
                    'product' => [
                        'id' => $product->id,
                        'nama' => $product->nama,
                        'harga' => $product->harga,
                        'gambar' => $product->gambar,
                        'stok' => $product->stok,
                        'closed' => $product->closed
                    ]
                ]
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menambahkan produk ke favorit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove the specified favorite from storage.
     */
    public function destroy($productId)
    {
        try {
            $customer = Auth::guard('customer')->user();
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pelanggan tidak diautentikasi'
                ], 401);
            }

            $favorite = FavoriteMenu::where('customer_id', $customer->id)
                ->where('product_id', $productId)
                ->first();

            if (!$favorite) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produk tidak ditemukan di favorit'
                ], 404);
            }

            $favorite->delete();

            return response()->json([
                'success' => true,
                'message' => 'Produk berhasil dihapus dari favorit'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus produk dari favorit',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check if a product is in customer's favorites.
     */
    public function checkFavorite($productId)
    {
        try {
            $customer = Auth::guard('customer')->user();
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Pelanggan tidak diautentikasi'
                ], 401);
            }

            $isFavorite = FavoriteMenu::where('customer_id', $customer->id)
                ->where('product_id', $productId)
                ->exists();

            return response()->json([
                'success' => true,
                'data' => [
                    'is_favorite' => $isFavorite
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Gagal memeriksa status favorit produk',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}