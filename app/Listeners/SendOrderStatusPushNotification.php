<?php

namespace App\Listeners;

use App\Events\OrderStatusChanged;
use App\Services\FirebaseService;
use App\Models\Transaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SendOrderStatusPushNotification implements ShouldQueue
{
    use InteractsWithQueue;

    protected $firebaseService;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 0;

    /**
     * Create the event listener.
     */
    public function __construct(FirebaseService $firebaseService)
    {
        $this->firebaseService = $firebaseService;
    }

    /**
     * Handle the event.
     */
    public function handle(OrderStatusChanged $event): void
    {
        // Create unique lock key for this notification
        $lockKey = "push_notification_{$event->transactionId}_{$event->status}";
        
        // Try to acquire lock for 10 seconds
        // If lock exists, skip (notification already sent/being sent)
        $lock = Cache::lock($lockKey, 10);
        
        if (!$lock->get()) {
            Log::info('Push notification already being processed, skipping duplicate', [
                'transaction_id' => $event->transactionId,
                'status' => $event->status,
            ]);
            return;
        }

        try {
            // Get transaction with customer relationship
            $transaction = Transaction::with('customer')->find($event->transactionId);
            
            if (!$transaction || !$transaction->customer) {
                Log::warning('Transaction or customer not found for push notification', [
                    'transaction_id' => $event->transactionId,
                ]);
                return;
            }

            // Get customer's FCM token
            $fcmToken = $transaction->customer->fcm_token;
            
            if (empty($fcmToken)) {
                Log::info('Customer has no FCM token', [
                    'customer_id' => $transaction->customer->id,
                    'transaction_id' => $transaction->id,
                ]);
                return;
            }

            // Send push notification based on status
            $this->firebaseService->sendOrderStatusNotification(
                $fcmToken,
                (string) $transaction->id,
                $transaction->status
            );

            Log::info('Order status push notification sent', [
                'transaction_id' => $transaction->id,
                'status' => $transaction->status,
                'customer_id' => $transaction->customer->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send order status push notification', [
                'transaction_id' => $event->transactionId,
                'error' => $e->getMessage(),
            ]);
        } finally {
            // Release the lock
            optional($lock)->release();
        }
    }
}
