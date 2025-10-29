<?php

namespace App\Services;

use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DuplicateOrderProtectionService
{
    /**
     * Time window in seconds to consider orders as duplicates
     */
    const DUPLICATE_TIME_WINDOW = 300; // 5 minutes

    /**
     * Maximum allowed attempts within time window
     */
    const MAX_ATTEMPTS_PER_WINDOW = 3;

    /**
     * Generate order hash from order data
     *
     * @param array $orderData
     * @return string
     */
    public function generateOrderHash(array $orderData): string
    {
        // Create a consistent hash from order data
        $hashData = [
            'customer_email' => $orderData['customer_email'] ?? '',
            'items' => $this->normalizeItems($orderData['items'] ?? []),
            'payment_method' => $orderData['payment_method'] ?? '',
            'customer_notes' => trim($orderData['customer_notes'] ?? ''),
            'discount_id' => $orderData['discount_id'] ?? null,
        ];

        // Sort items to ensure consistent hash regardless of order
        ksort($hashData['items']);

        // Manually sort keys to ensure consistent hash
        ksort($hashData);

        return hash('sha256', json_encode($hashData));
    }

    /**
     * Generate unique idempotency key
     *
     * @param string $customerEmail
     * @return string
     */
    public function generateIdempotencyKey(string $customerEmail): string
    {
        return hash('sha256', $customerEmail . microtime(true) . random_int(10000, 99999));
    }

    /**
     * Check if order is duplicate based on hash and time window
     *
     * @param string $customerEmail
     * @param string $orderHash
     * @return array
     */
    public function checkDuplicateOrder(string $customerEmail, string $orderHash): array
    {
        $timeLimit = Carbon::now()->subSeconds(self::DUPLICATE_TIME_WINDOW);

        // Check for recent identical orders
        $recentIdenticalOrder = Transaction::where('customer_email', $customerEmail)
            ->where('order_hash', $orderHash)
            ->where('created_at', '>=', $timeLimit)
            ->whereIn('status', ['pending', 'processing', 'completed'])
            ->first();

        if ($recentIdenticalOrder) {
            return [
                'is_duplicate' => true,
                'reason' => 'identical_order',
                'message' => 'Pesanan yang sama telah dibuat dalam 5 menit terakhir.',
                'existing_order' => $recentIdenticalOrder->kode_transaksi,
                'time_remaining' => $this->getTimeRemaining($recentIdenticalOrder->created_at)
            ];
        }

        // Check for too many attempts
        $recentAttempts = Transaction::where('customer_email', $customerEmail)
            ->where('last_attempt_at', '>=', $timeLimit)
            ->count();

        if ($recentAttempts >= self::MAX_ATTEMPTS_PER_WINDOW) {
            return [
                'is_duplicate' => true,
                'reason' => 'too_many_attempts',
                'message' => 'Terlalu banyak percobaan checkout. Silakan tunggu beberapa menit.',
                'time_remaining' => ceil(self::DUPLICATE_TIME_WINDOW / 60) . ' menit'
            ];
        }

        return [
            'is_duplicate' => false,
            'message' => 'Order valid untuk diproses'
        ];
    }

    /**
     * Check if idempotency key already exists
     *
     * @param string $idempotencyKey
     * @return Transaction|null
     */
    public function checkIdempotencyKey(string $idempotencyKey): ?Transaction
    {
        return Transaction::where('idempotency_key', $idempotencyKey)
            ->whereIn('status', ['pending', 'processing', 'completed'])
            ->first();
    }

    /**
     * Record checkout attempt
     *
     * @param string $customerEmail
     * @param string $orderHash
     * @return void
     */
    public function recordAttempt(string $customerEmail, string $orderHash): void
    {
        try {
            // Update or create attempt record
            Transaction::where('customer_email', $customerEmail)
                ->where('order_hash', $orderHash)
                ->where('status', 'failed')
                ->update(['last_attempt_at' => Carbon::now()]);
        } catch (\Exception $e) {
            Log::warning('Failed to record checkout attempt: ' . $e->getMessage());
        }
    }

    /**
     * Normalize items array for consistent hashing
     *
     * @param array $items
     * @return array
     */
    private function normalizeItems(array $items): array
    {
        $normalized = [];
        
        foreach ($items as $key => $item) {
            if (is_array($item)) {
                // Handle array format from request
                $productId = $item['id'] ?? $item['product_id'] ?? $key;
                $quantity = $item['quantity'] ?? $item['qty'] ?? 0;
            } else {
                // Handle simple key-value format
                $productId = $key;
                $quantity = $item;
            }

            if ($quantity > 0) {
                $normalized[$productId] = (int)$quantity;
            }
        }

        ksort($normalized);
        return $normalized;
    }

    /**
     * Get remaining time for duplicate order window
     *
     * @param Carbon $orderTime
     * @return string
     */
    private function getTimeRemaining(Carbon $orderTime): string
    {
        $timeElapsed = Carbon::now()->diffInSeconds($orderTime);
        $timeRemaining = self::DUPLICATE_TIME_WINDOW - $timeElapsed;
        
        if ($timeRemaining <= 0) {
            return '0 detik';
        }

        if ($timeRemaining >= 60) {
            return ceil($timeRemaining / 60) . ' menit';
        }

        return $timeRemaining . ' detik';
    }
}