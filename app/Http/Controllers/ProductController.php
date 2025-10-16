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
        return Inertia::render('Welcome', [
            'canLogin' => Route::has('login'),
            'canRegister' => Route::has('register'),
            'products' => Product::all()->map(function($product) {
                return [
                    'id' => $product->id,
                    'nama' => $product->nama,
                    'harga' => $product->harga,
                    'stok' => $product->stok,
                    'gambar' => $product->gambar ? asset('storage/' . $product->gambar) : null,
                ];
            })
        ]);
    }
}