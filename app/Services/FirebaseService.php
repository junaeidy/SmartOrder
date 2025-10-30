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
     * @return bool
     */
    public function sendNotification(string $fcmToken, string $title, string $body, array $data = []): bool
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
            ]);

            return true;
        } catch (MessagingException $e) {
            Log::error('Failed to send push notification: ' . $e->getMessage(), [
                'title' => $title,
                'body' => $body,
                'error' => $e->getMessage(),
            ]);
            return false;
        } catch (\Exception $e) {
            Log::error('Unexpected error sending notification: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send order status notification
     * 
     * @param string $fcmToken
     * @param string $orderId
     * @param string $status
     * @return bool
     */
    public function sendOrderStatusNotification(string $fcmToken, string $orderId, string $status): bool
    {
        $statusMessages = [
            'waiting' => 'Pesanan Anda sedang diproses',
            'awaiting_confirmation' => 'Pesanan Anda siap diambil',
            'completed' => 'Pesanan Anda telah selesai',
            'cancelled' => 'Pesanan Anda dibatalkan',
        ];

        $title = 'Status Pesanan';
        $body = $statusMessages[$status] ?? 'Status pesanan Anda berubah';

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
}
