<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Auth;
use App\Models\FavoriteMenu;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Check if product is in customer's favorites
        $isFavorite = false;
        $customer = Auth::guard('customer')->user();
        
        if ($customer) {
            // Use the favorite_menu relationship if it's loaded, otherwise query
            if ($this->relationLoaded('favoriteMenus')) {
                $isFavorite = $this->favoriteMenus->where('customer_id', $customer->id)->isNotEmpty();
            } else {
                $isFavorite = FavoriteMenu::where('customer_id', $customer->id)
                    ->where('product_id', $this->id)
                    ->exists();
            }
        }

        return [
            'id' => $this->id,
            'nama' => $this->nama,
            'harga' => $this->harga,
            'stok' => $this->stok,
            'closed' => (bool)($this->closed ?? false),
            'gambar' => $this->gambar ? asset('storage/' . $this->gambar) : null,
            'is_favorite' => $isFavorite,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
