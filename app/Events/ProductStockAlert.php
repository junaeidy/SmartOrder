<?php

namespace App\Events;

use App\Models\Product;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ProductStockAlert implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public array $product;
    public string $type; // low_stock | out_of_stock | closed | opened

    public function __construct(Product $product, string $type)
    {
        $this->product = [
            'id' => $product->id,
            'nama' => $product->nama,
            'stok' => $product->stok,
            'harga' => $product->harga,
            'closed' => (bool)($product->closed ?? false),
            'gambar' => $product->gambar ? asset('storage/' . $product->gambar) : null,
        ];
        $this->type = $type;
    }

    public function broadcastOn(): Channel
    {
        return new Channel('products');
    }

    public function broadcastAs(): string
    {
        return 'ProductStockAlert';
    }
}
