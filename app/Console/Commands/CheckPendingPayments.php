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
            
        $this->info('Found ' . $pendingTransactions->count() . ' pending payments');
        
        $updated = 0;
        $expired = 0;
        $failures = 0;
        
        foreach ($pendingTransactions as $transaction) {
            $this->info("Checking payment for transaction {$transaction->kode_transaksi}...");
            
            try {
                $response = $this->midtransService->checkTransactionStatus($transaction->kode_transaksi);
                
                if (!$response['success']) {
                    $this->error("Error checking transaction {$transaction->kode_transaksi}: {$response['message']}");
                    $failures++;
                    continue;
                }
                
                $status = $response['status'];
                $transactionStatus = $status->transaction_status ?? null;
                
                $this->info("Transaction {$transaction->kode_transaksi} status: {$transactionStatus}");
                
                if (in_array($transactionStatus, ['settlement', 'capture'])) {
                    // Payment successful
                    $transaction->payment_status = 'paid';
                    $transaction->paid_at = now();
                    
                    if ($transaction->status === 'waiting_for_payment') {
                        $transaction->status = 'waiting';
                    }
                    
                    $transaction->save();
                    $updated++;
                    
                    $this->info("Payment for transaction {$transaction->kode_transaksi} marked as paid");
                } elseif (in_array($transactionStatus, ['expire', 'cancel', 'deny', 'failure'])) {
                    // Payment failed or expired
                    $transaction->payment_status = 'failed';
                    $transaction->save();
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