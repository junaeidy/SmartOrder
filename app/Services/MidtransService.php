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
        // Robust boolean casting for env/config flags
        $bool = function ($value, $default = false) {
            if ($value === null) return (bool) $default;
            // Accept bool, int, string forms: true/false, 1/0, 'true'/'false', 'yes'/'no', 'on'/'off'
            if (is_bool($value)) return $value;
            return filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? (bool) $default;
        };
        Config::$isProduction = $bool(env('MIDTRANS_IS_PRODUCTION', config('midtrans.is_production')));
        Config::$isSanitized = $bool(env('MIDTRANS_IS_SANITIZED', config('midtrans.is_sanitized', true)));
        Config::$is3ds = $bool(env('MIDTRANS_IS_3DS', config('midtrans.is_3ds', true)));
        
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

        // Normalize helper to integer rupiah (no decimals)
        $toInt = function ($amount) {
            // Amounts might be strings/decimals; ensure proper integer conversion
            return (int) round((float) $amount, 0);
        };

        // Parse the items from the transaction
        $subtotal = 0;
        foreach ($transaction->items as $item) {
            $unitPrice = $toInt($item['harga'] ?? ($item['price'] ?? 0));
            $qty = (int) ($item['quantity'] ?? 0);
            $name = $item['nama'] ?? ($item['name'] ?? 'Item');
            $items[] = [
                'id' => (string) ($item['id'] ?? uniqid('ITM-')),
                'price' => $unitPrice,
                'quantity' => $qty,
                'name' => $name,
            ];
            $subtotal += ($unitPrice * $qty);
        }

        // Add discount as a separate line (negative price) if any
        $discountAmount = $toInt($transaction->discount_amount ?? 0);
        if ($discountAmount > 0) {
            $items[] = [
                'id' => 'DISCOUNT',
                'price' => -$discountAmount,
                'quantity' => 1,
                'name' => 'Diskon',
            ];
        }

        // Add tax as a separate line if any
        $taxAmount = $toInt($transaction->tax_amount ?? 0);
        if ($taxAmount > 0) {
            $items[] = [
                'id' => 'TAX',
                'price' => $taxAmount,
                'quantity' => 1,
                'name' => 'Pajak',
            ];
        }

        // Generate a unique transaction ID for Midtrans that is different from our internal ID
        // This will be the ID we use to check status with Midtrans API
        $midtransTransactionId = $transaction->kode_transaksi . '-' . uniqid();

        // Compute gross amount explicitly to ensure it matches items sum
        $grossAmount = $toInt($transaction->total_amount);

        $params = [
            'transaction_details' => [
                'order_id' => $midtransTransactionId, // Use the unique Midtrans transaction ID
                'gross_amount' => $grossAmount,
            ],
            'item_details' => $items,
            'customer_details' => [
                'first_name' => $transaction->customer_name,
                'email' => $transaction->customer_email,
                'phone' => $transaction->customer_phone,
            ],
            // Enable payment method switching
            'enabled_payments' => [
                'credit_card', 'bca_va', 'bni_va', 'bri_va', 'permata_va',
                'shopeepay', 'gopay', 'indomaret', 'alfamart', 'other_qris'
            ],
            // Set callback URLs
            'callbacks' => [
                'finish' => route('midtrans.finish', ['orderId' => $transaction->kode_transaksi]),
                'error' => route('midtrans.finish', ['orderId' => $transaction->kode_transaksi]),
                'pending' => route('midtrans.finish', ['orderId' => $transaction->kode_transaksi]),
            ],
            // Add this to ensure payment method switching is allowed
            'page_expiry' => [
                'duration' => 15, // 15 minutes timeout
                'unit' => 'minute'
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
                // Normalize to object for consistent property access
                $statusObj = is_array($status) ? (object) $status : $status;

                \Illuminate\Support\Facades\Log::info('Midtrans API response: ', ['status' => is_object($statusObj) ? (array) $statusObj : $statusObj]);

                // Update transaction in database
                // Normalize our payment_status while preserving original Midtrans status
                $transaction->midtrans_transaction_status = $statusObj->transaction_status ?? null;
                if (in_array($statusObj->transaction_status ?? '', ['settlement', 'capture'])) {
                    $transaction->payment_status = 'paid';
                    $transaction->status = 'waiting'; // Show in karyawan order management
                    $transaction->paid_at = now();
                } elseif (($statusObj->transaction_status ?? null) === 'pending') {
                    $transaction->payment_status = 'pending';
                } elseif (in_array($statusObj->transaction_status ?? '', ['deny', 'cancel'])) {
                    $transaction->payment_status = 'failed';
                } elseif (($statusObj->transaction_status ?? null) === 'expire') {
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
     * Verify Midtrans notification signature.
     * 
     * @param object $notification
     * @return bool
     * @throws \Exception
     */
    public function verifySignature($notification): bool
    {
        // Check if all required fields are present
        if (!isset($notification->signature_key, $notification->order_id, $notification->status_code, $notification->gross_amount)) {
            \Illuminate\Support\Facades\Log::error('Missing required fields for signature verification', [
                'notification' => (array) $notification,
            ]);
            throw new \Exception('Missing required fields for signature verification');
        }

        $serverKey = Config::$serverKey;
        $orderId = $notification->order_id;
        $statusCode = $notification->status_code;
        $grossAmount = $notification->gross_amount;
        $signatureKey = $notification->signature_key;

        // Create hash string according to Midtrans documentation
        $hashString = $orderId . $statusCode . $grossAmount . $serverKey;
        $computedSignature = hash('sha512', $hashString);

        // Use hash_equals for timing-safe comparison
        $isValid = hash_equals($computedSignature, $signatureKey);

        if (!$isValid) {
            \Illuminate\Support\Facades\Log::error('Invalid Midtrans signature', [
                'order_id' => $orderId,
                'expected' => substr($computedSignature, 0, 20) . '...',
                'received' => substr($signatureKey, 0, 20) . '...',
            ]);
        }

        return $isValid;
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

        // 🔒 CRITICAL: Verify signature (mandatory for security)
        try {
            if (!$this->verifySignature($notification)) {
                \Illuminate\Support\Facades\Log::warning('Midtrans signature verification failed', [
                    'order_id' => $notification->order_id ?? 'unknown',
                ]);
                return [
                    'success' => false,
                    'message' => 'Invalid signature',
                ];
            }
            \Illuminate\Support\Facades\Log::info('Midtrans signature verified successfully', [
                'order_id' => $notification->order_id,
            ]);
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Midtrans signature verification error', [
                'error' => $e->getMessage(),
                'notification' => (array) $notification,
            ]);
            return [
                'success' => false,
                'message' => 'Signature verification error: ' . $e->getMessage(),
            ];
        }
        
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

    /**
     * Expire a pending Midtrans transaction (useful for VA/QR/retail payments)
     *
     * @param string $midtransOrderId The Midtrans order_id previously stored in midtrans_transaction_id
     * @return array{success:bool,message?:string,response?:mixed}
     */
    public function expireTransaction(string $midtransOrderId): array
    {
        try {
            $response = MidtransTransaction::expire($midtransOrderId);
            \Illuminate\Support\Facades\Log::info('Midtrans expire success', [
                'order_id' => $midtransOrderId,
                'response' => $response,
            ]);
            return [
                'success' => true,
                'response' => $response,
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Midtrans expire failed', [
                'order_id' => $midtransOrderId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Cancel a Midtrans transaction (typically for credit card or specific cases)
     *
     * @param string $midtransOrderId
     * @return array{success:bool,message?:string,response?:mixed}
     */
    public function cancelTransaction(string $midtransOrderId): array
    {
        try {
            $response = MidtransTransaction::cancel($midtransOrderId);
            \Illuminate\Support\Facades\Log::info('Midtrans cancel success', [
                'order_id' => $midtransOrderId,
                'response' => $response,
            ]);
            return [
                'success' => true,
                'response' => $response,
            ];
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::warning('Midtrans cancel failed', [
                'order_id' => $midtransOrderId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'message' => $e->getMessage(),
            ];
        }
    }
}