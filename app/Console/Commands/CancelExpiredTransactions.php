<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Services\MidtransService;

class CancelExpiredTransactions extends Command
{
    protected $signature = 'transactions:cancel-expired';
    protected $description = 'Cancel expired Midtrans transactions that have not been paid within 15 minutes';

    public function handle()
    {
        $this->info('Checking for expired transactions...');
        
        try {
            // Get transactions that are pending and created more than 15 minutes ago
            $expiredIds = Transaction::where('payment_method', 'midtrans')
                ->where('payment_status', 'pending')
                ->where('status', '!=', 'cancelled') // Pastikan belum dibatalkan
                ->where('created_at', '<=', Carbon::now()->subMinutes(15))
                ->pluck('id');

            if ($expiredIds->isEmpty()) {
                $this->info('No expired transactions found.');
                return Command::SUCCESS;
            }

            $midtrans = new MidtransService();
            $processed = 0;

            foreach ($expiredIds as $id) {
                $transaction = null;
                $shouldNotify = false;

                try {
                    DB::transaction(function () use ($id, &$transaction, &$shouldNotify, $midtrans) {
                        // Lock the transaction row to avoid race conditions
                        $transaction = Transaction::where('id', $id)->lockForUpdate()->first();
                        if (!$transaction) {
                            return; // Already removed or not found
                        }

                        // Re-check conditions inside the lock
                        if ($transaction->payment_method !== 'midtrans') return;
                        if ($transaction->payment_status !== 'pending') return;
                        if ($transaction->status === 'cancelled') return;
                        if (!Carbon::parse($transaction->created_at)->addMinutes(15)->isPast()) return;

                        // Try to expire on Midtrans (ignore failures, just log)
                        if (!empty($transaction->midtrans_transaction_id)) {
                            $midtrans->expireTransaction($transaction->midtrans_transaction_id);
                        }

                        // Update transaction status locally
                        $oldStatus = $transaction->getOriginal('status');
                        $transaction->payment_status = 'expired';
                        $transaction->status = 'cancelled';
                        $transaction->cancelled_at = now();
                        $transaction->save();

                        Log::info("Updating transaction {$transaction->kode_transaksi} status", [
                            'old_status' => $oldStatus,
                            'new_status' => 'cancelled',
                            'payment_status' => 'expired'
                        ]);

                        // Restore product stock
                        $items = $transaction->items; // cast to array
                        if (is_array($items)) {
                            foreach ($items as $item) {
                                $product = \App\Models\Product::lockForUpdate()->find($item['id'] ?? null);
                                if ($product && isset($item['quantity'])) {
                                    $product->increment('stok', (int) $item['quantity']);
                                }
                            }
                        } else {
                            Log::warning("Transaction {$transaction->kode_transaksi} has invalid items format");
                        }

                        // Defer notifications to after commit
                        $shouldNotify = true;
                    });

                    if ($transaction && $shouldNotify) {
                        // Send cancellation email
                        try {
                            if ($transaction->customer_email) {
                                \Illuminate\Support\Facades\Mail::to($transaction->customer_email)
                                    ->send(new \App\Mail\OrderCancellation($transaction));
                                Log::info("Cancellation email sent for transaction: {$transaction->kode_transaksi}", [
                                    'email' => $transaction->customer_email
                                ]);
                            } else {
                                Log::warning("No customer email found for transaction: {$transaction->kode_transaksi}");
                            }
                        } catch (\Exception $emailError) {
                            Log::error("Failed to send cancellation email for transaction {$transaction->kode_transaksi}", [
                                'error' => $emailError->getMessage(),
                                'trace' => $emailError->getTraceAsString()
                            ]);
                        }

                        // Broadcast event untuk update realtime di frontend
                        event(new \App\Events\OrderStatusChanged($transaction));

                        // Log the cancellation
                        Log::info("Transaction {$transaction->kode_transaksi} cancelled due to payment timeout");
                        $this->info("Cancelled transaction: {$transaction->kode_transaksi}");
                        $processed++;
                    }
                } catch (\Throwable $e) {
                    Log::error("Error cancelling transaction ID {$id}: " . $e->getMessage(), [
                        'trace' => $e->getTraceAsString(),
                    ]);
                    $this->error("Error cancelling transaction ID {$id}: " . $e->getMessage());
                    // Continue with next ID
                }
            }

            $this->info("Processed {$processed} expired transactions");

        } catch (\Exception $e) {
            Log::error('Error in CancelExpiredTransactions command: ' . $e->getMessage());
            $this->error('Error: ' . $e->getMessage());
        }
    }

    public function __destruct()
    {
        // No-op
    }
}