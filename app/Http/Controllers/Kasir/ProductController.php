<?php

namespace App\Http\Controllers\Kasir;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Storage;
use App\Events\ProductStockAlert;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');

        $products = Product::query()
            ->when($search, fn($q) => $q->where('nama', 'like', "%{$search}%"))
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        // Map to include 'closed' flag on each item in paginator
        $products->getCollection()->transform(function ($p) {
            $p->closed = (bool)($p->closed ?? false);
            return $p;
        });

        return inertia('Kasir/Products/Index', [
            'products' => $products,
            'filters' => ['search' => $search],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nama' => 'required|string|max:255',
            'harga' => 'required|numeric',
            'stok' => 'required|integer',
            'gambar' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('gambar')) {
            $validated['gambar'] = $request->file('gambar')->store('produk', 'public');
        }

        Product::create($validated);
        return redirect()->back()->with('success', 'Produk berhasil ditambahkan.');
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'nama' => 'required|string',
            'harga' => 'required|numeric',
            'stok' => 'required|integer',
            'gambar' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('gambar')) {
            if ($product->gambar && Storage::disk('public')->exists($product->gambar)) {
                Storage::disk('public')->delete($product->gambar);
            }

            $data['gambar'] = $request->file('gambar')->store('produk', 'public');
        } else {
            $data['gambar'] = $product->gambar;
        }

        // Track previous values for threshold checks
        $prevStock = $product->stok;
        $product->update($data);

        // Broadcast alerts when crossing thresholds
        if ($product->stok <= 0 && $prevStock > 0) {
            event(new ProductStockAlert($product, 'out_of_stock'));
        } elseif ($product->stok <= 20 && $prevStock > 20) {
            event(new ProductStockAlert($product, 'low_stock'));
        }

        return back()->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product)
    {
        if ($product->gambar && Storage::disk('public')->exists($product->gambar)) {
            Storage::disk('public')->delete($product->gambar);
        }
        $product->delete();

        return redirect()->back()->with('success', 'Produk berhasil dihapus.');
    }

    // Toggle closed (hide from ordering but keep visible)
    public function toggleClosed(Product $product)
    {
        $product->closed = !$product->closed;
        $product->save();

        event(new ProductStockAlert($product, $product->closed ? 'closed' : 'opened'));

        return back()->with('success', $product->closed ? 'Produk ditutup sementara.' : 'Produk dibuka kembali.');
    }

    // Return current alerts snapshot (for initial load)
    public function alerts()
    {
        $low = Product::where('stok', '>', 0)->where('stok', '<=', 20)->get();
        $out = Product::where('stok', '<=', 0)->get();
        $closed = Product::where('closed', true)->get();

        return response()->json([
            'low' => $low->map(fn($p) => [
                'id' => $p->id,
                'nama' => $p->nama,
                'stok' => $p->stok,
                'closed' => (bool)$p->closed,
                'gambar' => $p->gambar,
            ]),
            'out' => $out->map(fn($p) => [
                'id' => $p->id,
                'nama' => $p->nama,
                'stok' => $p->stok,
                'closed' => (bool)$p->closed,
                'gambar' => $p->gambar,
            ]),
            'closed' => $closed->map(fn($p) => [
                'id' => $p->id,
                'nama' => $p->nama,
                'stok' => $p->stok,
                'closed' => (bool)$p->closed,
                'gambar' => $p->gambar,
            ]),
        ]);
    }
}
