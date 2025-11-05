<?php

namespace App\Jobs;

use App\Models\Customer;
use App\Services\FirebaseService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendAnnouncementNotification implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int
     */
    public $backoff = 10;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 30;

    protected $customerId;
    protected $title;
    protected $message;
    protected $announcementId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $customerId, string $title, string $message, ?int $announcementId = null)
    {
        $this->customerId = $customerId;
        $this->title = $title;
        $this->message = $message;
        $this->announcementId = $announcementId;
    }

    /**
     * Execute the job.
     */
    public function handle(FirebaseService $firebaseService): void
    {
        try {
            $customer = Customer::find($this->customerId);

            if (!$customer) {
                Log::warning('Pelanggan tidak ditemukan, tidak mengirim notifikasi', [
                    'customer_id' => $this->customerId,
                    'announcement_id' => $this->announcementId,
                ]);
                return;
            }

            if (empty($customer->fcm_token)) {
                Log::info('Pelanggan tidak memiliki token FCM, melewatkan notifikasi', [
                    'customer_id' => $this->customerId,
                    'announcement_id' => $this->announcementId,
                ]);
                return;
            }

            $result = $firebaseService->sendNotification(
                $customer->fcm_token,
                $this->title,
                $this->message,
                [
                    'type' => 'announcement',
                    'announcement_id' => $this->announcementId,
                ],
                $customer->id
            );

            if ($result) {
                Log::info('Notifikasi pengumuman berhasil dikirim', [
                    'customer_id' => $this->customerId,
                    'announcement_id' => $this->announcementId,
                ]);
            } else {
                Log::warning('Gagal mengirim notifikasi pengumuman', [
                    'customer_id' => $this->customerId,
                    'announcement_id' => $this->announcementId,
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Gagal mengirim notifikasi pengumuman', [
                'customer_id' => $this->customerId,
                'announcement_id' => $this->announcementId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Rethrow the exception to trigger retry
            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendAnnouncementNotification job gagal setelah semua percobaan', [
            'customer_id' => $this->customerId,
            'announcement_id' => $this->announcementId,
            'error' => $exception->getMessage(),
        ]);
    }
}
