<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Transaction;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderConfirmation;

class PaymentController extends Controller
{
    protected $midtransService;

    public function __construct(MidtransService $midtransService)
    {
        $this->midtransService = $midtransService;
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
                event(new \App\Events\NewOrderReceived($transaction));

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
            
            return response()->json([
                'success' => true,
                'data' => [
                    'transaction' => new OrderResource($transaction),
                    'status' => $transaction->payment_status,
                    'midtrans_status' => $transaction->midtrans_transaction_status,
                ]
            ]);
            
        } catch (\Exception $e) {
            Log::error('Error checking payment status: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error checking payment status: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Finish payment process
     */
    public function finish(Request $request)
    {
        // This endpoint is needed for the mobile app to redirect back after payment
        return response()->json([
            'success' => true,
            'message' => 'Payment process completed'
        ]);
    }
}
