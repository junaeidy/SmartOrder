<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderConfirmation;
use App\Helpers\BroadcastHelper;

class MidtransController extends Controller
{
    protected $midtransService;

    public function __construct(MidtransService $midtransService)
    {
        $this->midtransService = $midtransService;
    }

    /**
     * Handle transaction expiration
     */
    private function handleExpiredTransaction(Transaction $transaction)
    {
        // Idempotent: if already cancelled, do nothing
        if (!empty($transaction->cancelled_at)) {
            return;
        }

        $transaction->payment_status = 'expired';
        $transaction->status = 'cancelled';
        $transaction->cancelled_at = now();
        $transaction->save();

        // Restore product stock (array cast or JSON)
        $items = is_array($transaction->items) ? $transaction->items : json_decode($transaction->items ?? '[]', true);
        foreach ($items as $item) {
            $product = \App\Models\Product::find($item['id'] ?? null);
            if ($product && !empty($item['quantity'])) {
                $product->increment('stok', (int) $item['quantity']);
            }
        }

        // Send cancellation email
        try {
            Mail::to($transaction->customer_email)
                ->send(new \App\Mail\OrderCancellation($transaction));
            Log::info("Cancellation email sent for transaction: {$transaction->kode_transaksi}");
        } catch (\Exception $e) {
            Log::error("Failed to send cancellation email for transaction {$transaction->kode_transaksi}: " . $e->getMessage());
        }

        Log::info("Transaction {$transaction->kode_transaksi} marked as expired");
    }

    /**
     * Handle notification from Midtrans
     */
    public function notification(Request $request)
    {
        // Get notification data - sometimes it can be raw POST or JSON
        $notification = $request->isJson() ? $request->json()->all() : $request->all();
        
        if (empty($notification)) {
            $notification = json_decode($request->getContent(), true);
        }
        
        Log::info('Midtrans notification received: ' . json_encode($notification));
        
        // Debug the notification data to help troubleshoot
        if (isset($notification['order_id'])) {
            Log::info('Notification order_id: ' . $notification['order_id']);
            
            // Check if we have this midtrans_transaction_id in our database
            $transaction = Transaction::where('midtrans_transaction_id', $notification['order_id'])->first();
            if ($transaction) {
                Log::info('Found transaction by midtrans_transaction_id: ' . $transaction->kode_transaksi);
            } else {
                Log::warning('No transaction found with midtrans_transaction_id: ' . $notification['order_id']);
                
                // Try to find by kode_transaksi as fallback
                $transaction = Transaction::where('kode_transaksi', $notification['order_id'])->first();
                if ($transaction) {
                    Log::info('Found transaction by kode_transaksi: ' . $transaction->kode_transaksi);
                } else {
                    Log::error('Transaction not found by either midtrans_transaction_id or kode_transaksi');
                }
            }
        }

        $response = $this->midtransService->handleNotification($notification);
        
        if ($response["success"] && isset($response['transaction']) && $response['transaction']->payment_status == 'paid') {
            // If payment is successful, ensure status is 'waiting' and notify
            $transaction = $response['transaction'];
            $shouldNotify = empty($transaction->confirmation_email_sent_at);
            if ($transaction->status !== 'waiting') {
                $transaction->status = 'waiting';
                $transaction->save();
            }

            if ($shouldNotify) {
                // Notify the system that a new order has been paid (broadcast to UI)
                BroadcastHelper::safeBroadcast(new \App\Events\NewOrderReceived($transaction));

                // Send email confirmation synchronously (idempotent)
                try {
                    Mail::to($transaction->customer_email)->send(new OrderConfirmation($transaction));
                    $transaction->confirmation_email_sent_at = now();
                    $transaction->save();
                    Log::info('Order confirmation email sent for transaction: ' . $transaction->kode_transaksi);
                } catch (\Exception $e) {
                    Log::error('Error sending order confirmation email: ' . $e->getMessage());
                }
            } else {
                Log::info('Email already sent previously, skip duplicate send for: ' . $transaction->kode_transaksi);
            }

            Log::info('Ensured transaction status waiting and triggered event: ' . $transaction->kode_transaksi);
        } else {
            Log::warning('Could not process Midtrans notification: ' . 
                (isset($response['message']) ? $response['message'] : 'Unknown error'));
        }

        // Always return 200 success to Midtrans regardless of our processing result
        return response()->json(['status' => 'success']);
    }

    /**
     * Check status of a payment transaction
     */
    public function checkStatus(Request $request, $orderId)
    {
        try {
            // Log that we're checking the transaction
            Log::info('Checking payment status for transaction: ' . $orderId);
            
            // Use the updated service method which now returns the transaction object directly
            $response = $this->midtransService->checkTransactionStatus($orderId);
            
            // If no transaction was found in our database
            if (!isset($response['transaction'])) {
                Log::error('Transaction not found in database: ' . $orderId);
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found in database'
                ], 404);
            }
            
            // Get the transaction from response
            $transaction = $response['transaction'];
            
            // If Midtrans API call was successful
            if ($response['success']) {
                // Get the Midtrans status info
                $midtransStatus = $response['midtrans_status'];
                
                // Log the successful status check
                Log::info('Successfully retrieved payment status', [
                    'order_id' => $orderId,
                    'status' => $midtransStatus
                ]);

                // If now paid, ensure we finalize and send email notification (idempotent)
                if ($transaction->payment_status === 'paid') {
                    if ($transaction->status !== 'waiting') {
                        $transaction->status = 'waiting';
                        $transaction->save();
                    }
                    if (empty($transaction->confirmation_email_sent_at)) {
                        // First time confirmation: broadcast and email
                        BroadcastHelper::safeBroadcast(new \App\Events\NewOrderReceived($transaction));
                        try {
                            Mail::to($transaction->customer_email)->send(new OrderConfirmation($transaction));
                            $transaction->confirmation_email_sent_at = now();
                            $transaction->save();
                            Log::info('Email sent via checkStatus for transaction: ' . $transaction->kode_transaksi);
                        } catch (\Exception $e) {
                            Log::error('Email error via checkStatus: ' . $e->getMessage());
                        }
                    } else {
                        Log::info('Already confirmed previously (checkStatus), skip event/email for: ' . $transaction->kode_transaksi);
                    }
                }
                
                return response()->json([
                    'success' => true,
                    'transaction' => $transaction,
                    'midtrans_status' => $midtransStatus
                ]);
            } else {
                // Log the error from Midtrans service
                Log::error('Midtrans service error for transaction: ' . $orderId, [
                    'message' => $response['message'] ?? 'Unknown error'
                ]);
                
                // Return transaction data anyway, but note the Midtrans error
                return response()->json([
                    'success' => false,
                    'transaction' => $transaction,
                    'message' => $response['message'] ?? 'Gagal mengecek status transaksi dengan gateway pembayaran'
                ], 200); // Return 200 since we found the transaction in our database
            }
        } catch (\Exception $e) {
            // Log any unexpected errors
            Log::error('Exception in checkStatus: ', [
                'order_id' => $orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat mengecek status pembayaran'
            ], 500);
        }
    }
    
    /**
     * Handle finish redirect from Midtrans
     */
    public function finish(Request $request)
    {
        try {
            $orderId = $request->order_id;
            Log::info('Midtrans finish callback received', $request->all());
            
            // Find our transaction that's linked to this Midtrans transaction
            $transaction = Transaction::where('midtrans_transaction_id', $orderId)->first();
            
            // If we can't find by midtrans_transaction_id, try the original kode_transaksi as fallback
            if (!$transaction) {
                $transaction = Transaction::where('kode_transaksi', $orderId)->first();
            }
            
            if (!$transaction) {
                Log::error('Transaction not found for order ID: ' . $orderId);
                return redirect()->route('home')->with('error', 'Transaction not found');
            }
            
            // For logging and reference, use our internal transaction ID
            $internalOrderId = $transaction->kode_transaksi;
            
            // Check if payment is successful - using our internal ID
            $status = $this->midtransService->checkTransactionStatus($internalOrderId);
            
            // Log the status check result
            Log::info('Midtrans status check in finish method', [
                'internal_order_id' => $internalOrderId,
                'midtrans_transaction_id' => $orderId,
                'success' => $status['success'],
                'status' => $status['success'] ? json_encode($status['midtrans_status']) : null
            ]);
            
            // Get the updated transaction from the response
            if (isset($status['transaction'])) {
                $transaction = $status['transaction'];
            }
            
            if ($status['success'] && isset($status['midtrans_status']->transaction_status) && 
                in_array($status['midtrans_status']->transaction_status, ['capture', 'settlement'])) {
                
                // The transaction should already be updated by our service, but let's make sure
                // We need to check different status values that indicate successful payment
                if ($transaction->payment_status != 'paid' && 
                    $transaction->payment_status != 'settlement' && 
                    $transaction->payment_status != 'capture') {
                    $transaction->payment_status = 'paid'; // Always set to 'paid' for consistency
                    $transaction->midtrans_transaction_status = $status['midtrans_status']->transaction_status; // Keep the original status
                    $transaction->status = 'waiting'; // Change to 'waiting' so it appears in karyawan order management
                    $transaction->paid_at = now();
                    $transaction->save();
                }

                // Notify system about new paid order (broadcast to UI) only once
                $shouldNotify = empty($transaction->confirmation_email_sent_at);
                if ($shouldNotify) {
                    BroadcastHelper::safeBroadcast(new \App\Events\NewOrderReceived($transaction));
                    // Fallback: email only if not sent yet
                    if (empty($transaction->confirmation_email_sent_at)) {
                        try {
                            Mail::to($transaction->customer_email)->send(new OrderConfirmation($transaction));
                            $transaction->confirmation_email_sent_at = now();
                            $transaction->save();
                            Log::info('Fallback email sent in finish() for: ' . $transaction->kode_transaksi);
                        } catch (\Exception $e) {
                            Log::error('Fallback email error in finish(): ' . $e->getMessage());
                        }
                    }
                }
                
                // Ensure we have all the transaction data loaded
                $transaction->refresh();
                
                Log::info('Payment successful for order: ' . $internalOrderId);
                return redirect()->route('checkout.thankyou', $transaction->id)
                    ->with('success', 'Pembayaran berhasil! Pesanan Anda sedang diproses.');
            }
            
            // Payment might be pending or failed
            Log::info('Payment not yet confirmed for order: ' . $internalOrderId);
            return redirect()->route('checkout.thankyou', $transaction->id)
                ->with('warning', 'Kami sedang mengkonfirmasi pembayaran Anda. Silakan cek status pesanan nanti.');
            
        } catch (\Exception $e) {
            Log::error('Error in finish callback: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            
            return redirect()->route('home')
                ->with('error', 'An error occurred while processing your payment. Please contact support.');
        }
    }
}