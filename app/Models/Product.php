<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    use HasFactory;

    protected $fillable = [
        'nama',
        'harga',
        'stok',
        'gambar',
        'closed',
    ];

    protected $casts = [
        'stok' => 'string',
        'closed' => 'boolean',
    ];

    /**
     * Favorite menus relation for counting favorites per product.
     */
    public function favoriteMenus()
    {
        return $this->hasMany(\App\Models\FavoriteMenu::class, 'product_id');
    }

    /**
     * Customers who favorited this product
     */
    public function favoritedBy()
    {
        return $this->belongsToMany(Customer::class, 'favorite_menus', 'product_id', 'user_id')
            ->withTimestamps();
    }

    /**
     * Check if product is favorited by a customer
     */
    public function isFavoritedBy($customerId): bool
    {
        return $this->favoriteMenus()->where('user_id', $customerId)->exists();
    }

    /**
     * Get favorites count for this product
     */
    public function getFavoritesCountAttribute(): int
    {
        return $this->favoriteMenus()->count();
    }
}

