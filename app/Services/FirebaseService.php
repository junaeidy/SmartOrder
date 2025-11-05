<?php

namespace App\Services;

use Kreait\Firebase\Factory;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Kreait\Firebase\Exception\MessagingException;
use Illuminate\Support\Facades\Log;

class FirebaseService
{
    protected $messaging;

    public function __construct()
    {
        try {
            $serviceAccountPath = config('firebase.credentials.file');
            
            // Convert to absolute path if not already absolute
            if (!str_starts_with($serviceAccountPath, '/') && !preg_match('/^[a-zA-Z]:/', $serviceAccountPath)) {
                $serviceAccountPath = base_path($serviceAccountPath);
            }
            
            if (!file_exists($serviceAccountPath)) {
                Log::error('Firebase service account file not found', [
                    'path_from_config' => config('firebase.credentials.file'),
                    'absolute_path' => $serviceAccountPath,
                    'base_path' => base_path(),
                    'storage_path' => storage_path(),
                ]);
                $this->messaging = null;
                return;
            }

            $factory = (new Factory)->withServiceAccount($serviceAccountPath);
            $this->messaging = $factory->createMessaging();
            
            Log::info('Firebase messaging initialized successfully', [
                'credentials_path' => $serviceAccountPath,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to initialize Firebase', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            $this->messaging = null;
        }
    }

    /**
     * Send push notification to a user's device
     * 
     * @param string $fcmToken
     * @param string $title
     * @param string $body
     * @param array $data
     * @param int|null $customerId Optional customer ID for cleaning up invalid tokens
     * @return bool
     */
    public function sendNotification(string $fcmToken, string $title, string $body, array $data = [], ?int $customerId = null): bool
    {
        if (!$this->messaging) {
            Log::error('Firebase messaging not initialized');
            return false;
        }

        if (empty($fcmToken)) {
            Log::warning('FCM token is empty');
            return false;
        }

        try {
            $message = CloudMessage::withTarget('token', $fcmToken)
                ->withNotification(Notification::create($title, $body))
                ->withData($data);

            $this->messaging->send($message);
            
            Log::info('Push notification sent successfully', [
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'customer_id' => $customerId,
            ]);

            return true;
        } catch (MessagingException $e) {
            $errorMessage = $e->getMessage();
            
            Log::error('Failed to send push notification: ' . $errorMessage, [
                'title' => $title,
                'body' => $body,
                'error' => $errorMessage,
                'fcm_token' => substr($fcmToken, 0, 20) . '...', // Log partial token for debugging
                'customer_id' => $customerId,
            ]);

            // Check if token is invalid and clean it up
            if ($this->isInvalidTokenError($errorMessage) && $customerId) {
                $this->cleanupInvalidToken($customerId, $fcmToken);
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Unexpected error sending notification: ' . $e->getMessage(), [
                'customer_id' => $customerId,
                'fcm_token' => substr($fcmToken, 0, 20) . '...',
            ]);
            return false;
        }
    }

    /**
     * Check if error message indicates an invalid or expired token
     * 
     * @param string $errorMessage
     * @return bool
     */
    private function isInvalidTokenError(string $errorMessage): bool
    {
        $invalidTokenPatterns = [
            'Requested entity was not found',
            'Registration token is not a valid',
            'The registration token is not a valid FCM registration token',
            'Invalid registration token',
            'Unregistered',
        ];

        foreach ($invalidTokenPatterns as $pattern) {
            if (stripos($errorMessage, $pattern) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clean up invalid FCM token from database
     * 
     * @param int $customerId
     * @param string $fcmToken
     * @return void
     */
    private function cleanupInvalidToken(int $customerId, string $fcmToken): void
    {
        try {
            $customer = \App\Models\Customer::find($customerId);
            
            if ($customer && $customer->fcm_token === $fcmToken) {
                $customer->fcm_token = null;
                $customer->save();
                
                Log::info('Cleaned up invalid FCM token', [
                    'customer_id' => $customerId,
                    'token_preview' => substr($fcmToken, 0, 20) . '...',
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to cleanup invalid token', [
                'customer_id' => $customerId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Send order status notification
     * 
     * @param string $fcmToken
     * @param string $orderId
     * @param string $status
     * @param string|null $paymentMethod Optional payment method (tunai, online, etc.)
     * @return bool
     */
    public function sendOrderStatusNotification(string $fcmToken, string $orderId, string $status, ?string $paymentMethod = null): bool
    {
        $statusMessages = [
            'waiting_for_payment' => 'Pesanan berhasil dibuat! Silakan selesaikan pembayaran Anda.',
            'waiting' => 'Pembayaran berhasil! Pesanan Anda sedang diproses.',
            'waiting_cash' => 'Pesanan berhasil dibuat! Silakan menunggu.',
            'awaiting_confirmation' => 'Pesanan Anda siap diambil!',
            'completed' => 'Pesanan Anda telah selesai. Terima kasih!',
            'cancelled' => 'Pesanan Anda dibatalkan.',
        ];

        // Adjust message for tunai payment on 'waiting' status
        if ($status === 'waiting' && $paymentMethod === 'cash') {
            $body = $statusMessages['waiting_cash'];
        } else {
            $body = $statusMessages[$status] ?? 'Status pesanan Anda berubah';
        }

        $title = 'Status Pesanan';

        return $this->sendNotification($fcmToken, $title, $body, [
            'type' => 'order_status',
            'order_id' => $orderId,
            'status' => $status,
        ]);
    }

    /**
     * Send payment confirmation notification
     * 
     * @param string $fcmToken
     * @param string $orderId
     * @return bool
     */
    public function sendPaymentConfirmationNotification(string $fcmToken, string $orderId): bool
    {
        return $this->sendNotification(
            $fcmToken,
            'Pembayaran Berhasil',
            "Pembayaran untuk pesanan #{$orderId} telah diterima",
            [
                'type' => 'payment_confirmed',
                'order_id' => $orderId,
            ]
        );
    }

    /**
     * Send order ready notification
     * 
     * @param string $fcmToken
     * @param string $orderId
     * @param string $queueNumber
     * @return bool
     */
    public function sendOrderReadyNotification(string $fcmToken, string $orderId, string $queueNumber): bool
    {
        return $this->sendNotification(
            $fcmToken,
            'Pesanan Siap',
            "Pesanan #{$orderId} siap diambil - Nomor Antrian: {$queueNumber}",
            [
                'type' => 'order_ready',
                'order_id' => $orderId,
                'queue_number' => $queueNumber,
            ]
        );
    }

    /**
     * Send promo notification
     * 
     * @param string $fcmToken
     * @param string $title
     * @param string $body
     * @param string $promoId
     * @return bool
     */
    public function sendPromoNotification(string $fcmToken, string $title, string $body, string $promoId = ''): bool
    {
        return $this->sendNotification(
            $fcmToken,
            $title,
            $body,
            [
                'type' => 'promo',
                'promo_id' => $promoId,
            ]
        );
    }

    /**
     * Send notification to multiple devices
     * 
     * @param array $fcmTokens
     * @param string $title
     * @param string $body
     * @param array $data
     * @return int Number of successful sends
     */
    public function sendToMultipleDevices(array $fcmTokens, string $title, string $body, array $data = []): int
    {
        $successCount = 0;
        
        foreach ($fcmTokens as $token) {
            if ($this->sendNotification($token, $title, $body, $data)) {
                $successCount++;
            }
        }

        return $successCount;
    }

    /**
     * Send announcement notification to all customers with FCM tokens
     * 
     * @param string $title
     * @param string $message
     * @param int|null $announcementId
     * @return array ['success' => int, 'failed' => int, 'total' => int]
     */
    public function sendAnnouncementToAllCustomers(string $title, string $message, ?int $announcementId = null): array
    {
        // Get all customers with FCM tokens
        $customers = \App\Models\Customer::whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->get();

        $total = $customers->count();
        $successCount = 0;
        $failedCount = 0;
        $failedCustomers = [];

        foreach ($customers as $customer) {
            $result = $this->sendNotification(
                $customer->fcm_token,
                $title,
                $message,
                [
                    'type' => 'announcement',
                    'announcement_id' => $announcementId,
                ],
                $customer->id // Pass customer ID for token cleanup
            );

            if ($result) {
                $successCount++;
            } else {
                $failedCount++;
                $failedCustomers[] = [
                    'id' => $customer->id,
                    'name' => $customer->name,
                    'email' => $customer->email,
                ];
            }
        }

        Log::info('Announcement broadcast completed', [
            'announcement_id' => $announcementId,
            'total' => $total,
            'success' => $successCount,
            'failed' => $failedCount,
            'failed_customers' => $failedCustomers,
        ]);

        return [
            'total' => $total,
            'success' => $successCount,
            'failed' => $failedCount,
        ];
    }
}

