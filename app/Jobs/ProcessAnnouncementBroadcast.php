<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessAnnouncementBroadcast implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    /**
     * The maximum number of seconds the job can run.
     *
     * @var int
     */
    public $timeout = 300;

    protected $announcementId;

    /**
     * Create a new job instance.
     */
    public function __construct(int $announcementId)
    {
        $this->announcementId = $announcementId;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $announcement = Announcement::find($this->announcementId);

            if (!$announcement) {
                Log::error('Pengumuman tidak ditemukan', [
                    'announcement_id' => $this->announcementId,
                ]);
                return;
            }

            // Get all customers with FCM tokens
            $customers = Customer::whereNotNull('fcm_token')
                ->where('fcm_token', '!=', '')
                ->get();

            $total = $customers->count();

            Log::info('Memulai siaran pengumuman', [
                'announcement_id' => $this->announcementId,
                'total_recipients' => $total,
            ]);

            // Update announcement to sending status
            $announcement->update([
                'status' => 'sending',
                'recipients_count' => $total,
            ]);

            // Dispatch individual notification jobs
            foreach ($customers as $customer) {
                SendAnnouncementNotification::dispatch(
                    $customer->id,
                    $announcement->title,
                    $announcement->message,
                    $announcement->id
                );
            }

            // Update announcement status to sent
            // Note: success/failed counts will be tracked differently
            $announcement->update([
                'status' => 'sent',
                'sent_at' => now(),
            ]);

            Log::info('Siaran pengumuman berhasil disiarkan', [
                'announcement_id' => $this->announcementId,
                'total_jobs' => $total,
            ]);
        } catch (\Exception $e) {
            Log::error('Gagal memproses siaran pengumuman', [
                'announcement_id' => $this->announcementId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Update announcement to failed status
            if (isset($announcement)) {
                $announcement->update(['status' => 'failed']);
            }

            throw $e;
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('ProcessAnnouncementBroadcast job gagal', [
            'announcement_id' => $this->announcementId,
            'error' => $exception->getMessage(),
        ]);

        // Update announcement to failed status
        $announcement = Announcement::find($this->announcementId);
        if ($announcement) {
            $announcement->update(['status' => 'failed']);
        }
    }
}
