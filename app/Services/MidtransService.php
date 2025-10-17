<?php

namespace App\Services;

use Midtrans\Config;
use Midtrans\Snap;
use Midtrans\Transaction as MidtransTransaction;
use App\Models\Transaction as TransactionModel;

class MidtransService
{
    public function __construct()
    {
        // Set Midtrans configuration
        Config::$serverKey = env('MIDTRANS_SERVER_KEY', config('midtrans.server_key'));
        Config::$isProduction = env('MIDTRANS_IS_PRODUCTION', config('midtrans.is_production')) === 'true';
        Config::$isSanitized = env('MIDTRANS_IS_SANITIZED', config('midtrans.is_sanitized')) === 'true';
        Config::$is3ds = env('MIDTRANS_IS_3DS', config('midtrans.is_3ds')) === 'true';
        
        // For debugging
        \Illuminate\Support\Facades\Log::info('Midtrans Config: ', [
            'serverKey' => Config::$serverKey,
            'isProduction' => Config::$isProduction,
        ]);
    }

    /**
     * Create a Midtrans payment transaction
     * 
     * @param TransactionModel $transaction
     * @return array
     */
    public function createTransaction(TransactionModel $transaction)
    {
        $items = [];

        // Parse the items from the transaction
        foreach ($transaction->items as $item) {
            $items[] = [
                'id' => $item['id'],
                'price' => $item['harga'],
                'quantity' => $item['quantity'],
                'name' => $item['nama'],
            ];
        }

        // Generate a unique transaction ID for Midtrans that is different from our internal ID
        // This will be the ID we use to check status with Midtrans API
        $midtransTransactionId = $transaction->kode_transaksi . '-' . uniqid();

        $params = [
            'transaction_details' => [
                'order_id' => $midtransTransactionId, // Use the unique Midtrans transaction ID
                'gross_amount' => $transaction->total_amount,
            ],
            'item_details' => $items,
            'customer_details' => [
                'first_name' => $transaction->customer_name,
                'email' => $transaction->customer_email,
                'phone' => $transaction->customer_phone,
            ],
        ];
        
        // Save the Midtrans transaction ID to our transaction record
        $transaction->midtrans_transaction_id = $midtransTransactionId;
        $transaction->save();

        try {
            // Log parameters for debugging
            \Illuminate\Support\Facades\Log::info('Midtrans Request Parameters:', $params);
            
            // Create Snap payment page
            $snapResponse = Snap::createTransaction($params);
            $snapToken = $snapResponse->token;
            $snapUrl = $snapResponse->redirect_url;
            
            \Illuminate\Support\Facades\Log::info('Midtrans Response:', [
                'token' => $snapToken,
                'url' => $snapUrl
            ]);

            // Update the transaction with Midtrans details
            $transaction->update([
                'midtrans_payment_url' => $snapUrl,
            ]);

            return [
                'success' => true,
                'snap_token' => $snapToken,
                'redirect_url' => $snapUrl,
                'transaction' => $transaction
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Midtrans Error:', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'transaction' => $transaction
            ];
        }
    }

    /**
     * Check status of a Midtrans transaction
     * 
     * @param string $orderId
     * @return array
     */
    public function checkTransactionStatus($orderId)
    {
        try {
            // Log the order ID we're checking
            \Illuminate\Support\Facades\Log::info('Checking Midtrans transaction status for order: ' . $orderId);
            
            // Get the transaction from our database
            $transaction = TransactionModel::where('kode_transaksi', $orderId)->first();
            
            if (!$transaction) {
                \Illuminate\Support\Facades\Log::error('Transaction not found in database: ' . $orderId);
                return [
                    'success' => false,
                    'message' => 'Transaction not found in database',
                    'transaction' => null
                ];
            }
            
            // Check if the transaction has a Midtrans ID
            if (empty($transaction->midtrans_transaction_id)) {
                \Illuminate\Support\Facades\Log::warning('No Midtrans transaction ID for order: ' . $orderId);
                return [
                    'success' => false,
                    'message' => 'No Midtrans transaction ID associated with this order',
                    'transaction' => $transaction
                ];
            }
            
            try {
                // Use the midtrans_transaction_id to check status with Midtrans
                $status = MidtransTransaction::status($transaction->midtrans_transaction_id);
                
                \Illuminate\Support\Facades\Log::info('Midtrans API response: ', ['status' => $status]);
                
                // Update transaction in database
                // Normalize our payment_status while preserving original Midtrans status
                $transaction->midtrans_transaction_status = $status->transaction_status;
                if (in_array($status->transaction_status, ['settlement', 'capture'])) {
                    $transaction->payment_status = 'paid';
                    $transaction->status = 'waiting'; // Show in karyawan order management
                    $transaction->paid_at = now();
                } elseif ($status->transaction_status === 'pending') {
                    $transaction->payment_status = 'pending';
                } elseif (in_array($status->transaction_status, ['deny', 'cancel'])) {
                    $transaction->payment_status = 'failed';
                } elseif ($status->transaction_status === 'expire') {
                    $transaction->payment_status = 'expired';
                } else {
                    // Fallback to pending for any unknown status
                    $transaction->payment_status = 'pending';
                }
                $transaction->save();
                
                return [
                    'success' => true,
                    'message' => 'Success',
                    'transaction' => $transaction,
                    'midtrans_status' => $status
                ];
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Midtrans API error: ' . $e->getMessage(), [
                    'order_id' => $orderId,
                    'midtrans_id' => $transaction->midtrans_transaction_id
                ]);
                
                // Still return the transaction data even if Midtrans API fails
                return [
                    'success' => false,
                    'message' => 'Midtrans API error: ' . $e->getMessage(),
                    'transaction' => $transaction
                ];
            }
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error checking transaction status: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Error: ' . $e->getMessage(),
                'transaction' => null
            ];
        }
    }

    /**
     * Handle notification callback from Midtrans
     * 
     * @param array $notification
     * @return array
     */
    public function handleNotification($notification)
    {
        // Convert to object if it's an array
        if (is_array($notification)) {
            $notification = (object) $notification;
        }
        
        \Illuminate\Support\Facades\Log::info('Received Midtrans notification: ', (array) $notification);
        
        // Find transaction by the midtrans_transaction_id (not our kode_transaksi)
        $transaction = TransactionModel::where('midtrans_transaction_id', $notification->order_id)->first();

        if (!$transaction) {
            \Illuminate\Support\Facades\Log::error('Transaction not found for Midtrans order_id: ' . $notification->order_id);
            return [
                'success' => false,
                'message' => 'Transaction not found for given Midtrans transaction ID'
            ];
        }
        
        \Illuminate\Support\Facades\Log::info('Found transaction: ', [
            'id' => $transaction->id,
            'kode_transaksi' => $transaction->kode_transaksi
        ]);

        $transactionStatus = $notification->transaction_status;
        $paymentType = $notification->payment_type;
        $fraudStatus = isset($notification->fraud_status) ? $notification->fraud_status : null;

        $vaNumbers = null;
        if (isset($notification->va_numbers)) {
            $vaNumbers = $notification->va_numbers;
        }

        // Handle transaction status
        if ($transactionStatus == 'capture') {
            if ($fraudStatus == 'challenge') {
                // Transaction is challenged by FDS
                $transaction->payment_status = 'challenge';
            } else if ($fraudStatus == 'accept') {
                // Transaction is accepted
                $transaction->payment_status = 'paid'; // Always set to 'paid' for consistency
                $transaction->midtrans_transaction_status = $transactionStatus; // Store the original Midtrans status
                $transaction->status = 'waiting'; // Change status to 'waiting' for karyawan
                $transaction->paid_at = now();
            }
        } else if ($transactionStatus == 'settlement') {
            // Transaction is settled
            $transaction->payment_status = 'paid'; // Always set to 'paid' for consistency
            $transaction->midtrans_transaction_status = $transactionStatus; // Store the original Midtrans status
            $transaction->status = 'waiting'; // Change status to 'waiting' for karyawan to see in order management
            $transaction->paid_at = now();
        } else if ($transactionStatus == 'pending') {
            // Transaction is pending
            $transaction->payment_status = 'pending';
        } else if ($transactionStatus == 'deny') {
            // Transaction is denied
            $transaction->payment_status = 'failed';
        } else if ($transactionStatus == 'expire') {
            // Transaction is expired
            $transaction->payment_status = 'expired';
        } else if ($transactionStatus == 'cancel') {
            // Transaction is canceled
            $transaction->payment_status = 'failed';
        }

        $transaction->midtrans_transaction_status = $transactionStatus;
        $transaction->midtrans_payment_type = $paymentType;
        $transaction->save();

        return [
            'success' => true,
            'transaction' => $transaction
        ];
    }
}