<?php

namespace App\Policies;

use App\Models\Customer;
use App\Models\Transaction;
use App\Models\User;

class TransactionPolicy
{
    /**
     * Determine if the customer can view their own transaction.
     */
    public function view(Customer $customer, Transaction $transaction): bool
    {
        // Customer can only view their own transactions
        return $transaction->customer_email === $customer->email;
    }

    /**
     * Determine if the customer can view any transactions.
     */
    public function viewAny(Customer $customer): bool
    {
        // Customer can view their own transaction list
        return true;
    }

    /**
     * Determine if the customer can create a transaction.
     */
    public function create(Customer $customer): bool
    {
        // Any authenticated customer can create transactions
        return true;
    }

    /**
     * Determine if staff can view transaction.
     */
    public function viewAsStaff(User $user, Transaction $transaction): bool
    {
        // Kasir and karyawan can view all transactions
        return in_array($user->role, ['kasir', 'karyawan']);
    }

    /**
     * Determine if staff can view any transactions.
     */
    public function viewAnyAsStaff(User $user): bool
    {
        // Kasir and karyawan can view all transactions
        return in_array($user->role, ['kasir', 'karyawan']);
    }

    /**
     * Determine if the user can confirm a transaction.
     */
    public function confirm(User $user, Transaction $transaction): bool
    {
        // Only kasir can confirm transactions
        return $user->role === 'kasir';
    }

    /**
     * Determine if the user can cancel a transaction.
     */
    public function cancel(User $user, Transaction $transaction): bool
    {
        // Only kasir can cancel transactions
        return $user->role === 'kasir';
    }

    /**
     * Determine if the user can process an order.
     */
    public function process(User $user, Transaction $transaction): bool
    {
        // Only karyawan can process orders (mark as ready)
        return $user->role === 'karyawan';
    }

    /**
     * Determine if the user can view reports.
     */
    public function viewReports(User $user): bool
    {
        // Only kasir can view reports
        return $user->role === 'kasir';
    }

    /**
     * Determine if the user can export reports.
     */
    public function exportReports(User $user): bool
    {
        // Only kasir can export reports
        return $user->role === 'kasir';
    }

    /**
     * Determine if customer can cancel their own pending transaction.
     */
    public function cancelOwn(Customer $customer, Transaction $transaction): bool
    {
        // Customer can cancel their own pending payment
        return $transaction->customer_email === $customer->email 
            && $transaction->payment_status === 'pending'
            && $transaction->status === 'pending';
    }
}
