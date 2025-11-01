<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    /**
     * Determine if the given product can be viewed by the user.
     */
    public function view(?Customer $customer, Product $product): bool
    {
        // Everyone can view products (public)
        return true;
    }

    /**
     * Determine if the user can view any products.
     */
    public function viewAny(?Customer $customer): bool
    {
        // Everyone can view products list (public)
        return true;
    }

    /**
     * Determine if the user can create products.
     */
    public function create(User $user): bool
    {
        // Only kasir can create products
        return $user->role === 'kasir';
    }

    /**
     * Determine if the user can update the given product.
     */
    public function update(User $user, Product $product): bool
    {
        // Only kasir can update products
        return $user->role === 'kasir';
    }

    /**
     * Determine if the user can delete the given product.
     */
    public function delete(User $user, Product $product): bool
    {
        // Only kasir can delete products
        return $user->role === 'kasir';
    }

    /**
     * Determine if the user can restore the given product.
     */
    public function restore(User $user, Product $product): bool
    {
        return $user->role === 'kasir';
    }

    /**
     * Determine if the user can permanently delete the given product.
     */
    public function forceDelete(User $user, Product $product): bool
    {
        return $user->role === 'kasir';
    }

    /**
     * Determine if the user can toggle product availability.
     */
    public function toggleClosed(User $user, Product $product): bool
    {
        // Only kasir can toggle product availability
        return $user->role === 'kasir';
    }

    /**
     * Determine if the user can manage stock alerts.
     */
    public function manageStockAlerts(User $user): bool
    {
        // Only kasir can manage stock alerts
        return $user->role === 'kasir';
    }
}
