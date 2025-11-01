<?php

namespace App\Policies;

use App\Models\Discount;
use App\Models\User;

class DiscountPolicy
{
    /**
     * Determine if the user can view any discounts.
     */
    public function viewAny(?User $user): bool
    {
        // Public can view available discounts
        return true;
    }

    /**
     * Determine if the user can view the discount.
     */
    public function view(?User $user, Discount $discount): bool
    {
        // Public can view active discounts
        return $discount->is_active;
    }

    /**
     * Determine if the user can create discounts.
     */
    public function create(User $user): bool
    {
        // Only kasir can create discounts
        return $user->role === 'kasir';
    }

    /**
     * Determine if the user can update the discount.
     */
    public function update(User $user, Discount $discount): bool
    {
        // Only kasir can update discounts
        return $user->role === 'kasir';
    }

    /**
     * Determine if the user can delete the discount.
     */
    public function delete(User $user, Discount $discount): bool
    {
        // Only kasir can delete discounts
        return $user->role === 'kasir';
    }

    /**
     * Determine if the user can toggle discount active status.
     */
    public function toggle(User $user, Discount $discount): bool
    {
        // Only kasir can toggle discount status
        return $user->role === 'kasir';
    }

    /**
     * Determine if the user can manage discount schedules.
     */
    public function manageSchedule(User $user): bool
    {
        // Only kasir can manage discount schedules
        return $user->role === 'kasir';
    }
}
