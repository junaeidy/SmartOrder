<?php

namespace App\Console\Commands;

use App\Models\Transaction;
use App\Services\MidtransService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class CheckPendingPayments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'payments:check-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check the status of pending payments and update them';

    /**
     * Create a new command instance.
     */
    public function __construct(protected MidtransService $midtransService)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Checking pending payments...');
        
        // Get transactions with pending payment status and midtrans payment method
        // that are less than 24 hours old
        $pendingTransactions = Transaction::where('payment_status', 'pending')
            ->where('payment_method', 'midtrans')
            ->where('created_at', '>=', Carbon::now()->subDay())
            ->get();
            
        // Get transactions with waiting_for_payment status that are older than 15 minutes
        $expiredPayments = Transaction::where('status', 'waiting_for_payment')
            ->where('payment_method', 'midtrans')
            ->where('created_at', '<=', Carbon::now()->subMinutes(15))
            ->get();
            
        $this->info('Found ' . $pendingTransactions->count() . ' pending payments');
        $this->info('Found ' . $expiredPayments->count() . ' expired payments (>15 minutes)');
        
        $updated = 0;
        $expired = 0;
        $failures = 0;
        
        // Process expired payments first
        foreach ($expiredPayments as $transaction) {
            $this->info("Canceling expired payment for transaction {$transaction->kode_transaksi}...");
            
            try {
                // Restore stock
                if (!empty($transaction->items)) {
                    foreach ($transaction->items as $item) {
                        $product = \App\Models\Product::find($item['id']);
                        if ($product) {
                            $product->stok += $item['quantity'];
                            $product->save();
                            $this->info("Restored {$item['quantity']} stock for product {$product->nama}");
                        }
                    }
                }
                
                // Update transaction status
                $transaction->status = 'canceled';
                $transaction->payment_status = 'expired';
                $transaction->updated_at = Carbon::now();
                $transaction->save();
                
                // Trigger event
                event(new \App\Events\OrderStatusChanged($transaction));
                
                $expired++;
                $this->info("Transaction {$transaction->kode_transaksi} marked as expired and canceled");
            } catch (\Exception $e) {
                $this->error("Error canceling transaction {$transaction->kode_transaksi}: {$e->getMessage()}");
                $failures++;
            }
        }
        
        foreach ($pendingTransactions as $transaction) {
            $this->info("Checking payment for transaction {$transaction->kode_transaksi}...");
            
            try {
                $response = $this->midtransService->checkTransactionStatus($transaction->kode_transaksi);
                
                if (!$response['success']) {
                    $this->error("Error checking transaction {$transaction->kode_transaksi}: {$response['message']}");
                    $failures++;
                    continue;
                }
                
                // Service returns 'midtrans_status' (object/array) and updates DB transaction
                $statusPayload = $response['midtrans_status'] ?? null;
                $statusObj = is_array($statusPayload) ? (object) $statusPayload : $statusPayload;
                $transactionStatus = $statusObj->transaction_status ?? null;
                
                $this->info("Transaction {$transaction->kode_transaksi} status: {$transactionStatus}");
                
                if (in_array($transactionStatus, ['settlement', 'capture'])) {
                    // Payment successful (should already be updated in service)
                    $updated++;
                    $this->info("Payment for transaction {$transaction->kode_transaksi} marked as paid");
                } elseif (in_array($transactionStatus, ['expire', 'cancel', 'deny', 'failure'])) {
                    $expired++;
                    $this->info("Payment for transaction {$transaction->kode_transaksi} marked as failed/expired");
                }
                
            } catch (\Exception $e) {
                $this->error("Error checking transaction {$transaction->kode_transaksi}: {$e->getMessage()}");
                Log::error("Error checking payment for transaction {$transaction->kode_transaksi}: {$e->getMessage()}");
                $failures++;
            }
        }
        
        $this->info("Payment check complete:");
        $this->info("- Updated to paid: {$updated}");
        $this->info("- Expired/failed: {$expired}");
        $this->info("- Failures: {$failures}");
        
        return Command::SUCCESS;
    }
}