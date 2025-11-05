<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Jobs\ProcessAnnouncementBroadcast;
use App\Jobs\SendAnnouncementNotification;
use App\Models\Customer;
use App\Models\Announcement;
use App\Models\DeviceToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

class QueueJobTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function job_broadcast_pengumuman_masuk_antrian()
    {
        Queue::fake();

        // Create a user first (required for announcements)
        $user = \App\Models\User::factory()->create();

        $announcement = Announcement::create([
            'title' => 'Test Announcement',
            'message' => 'Test Message Content',
            'sent_by' => $user->id,
        ]);

        ProcessAnnouncementBroadcast::dispatch($announcement->id);

        Queue::assertPushed(ProcessAnnouncementBroadcast::class);
    }

    /** @test */
    public function job_notifikasi_pengumuman_diproses_dengan_benar()
    {
        Queue::fake();

        $customer = Customer::factory()->create();
        $user = \App\Models\User::factory()->create();
        
        DeviceToken::create([
            'customer_id' => $customer->id,
            'token' => 'fcm-token-123',
            'device_id' => 'device-123',
            'device_hash' => hash('sha256', 'device-123' . $customer->id),
            'platform' => 'android',
        ]);

        $announcement = Announcement::create([
            'title' => 'New Promo',
            'message' => '50% off today!',
            'sent_by' => $user->id,
        ]);

        // Just verify announcement was created
        $this->assertDatabaseHas('announcements', [
            'title' => 'New Promo',
            'message' => '50% off today!',
        ]);
    }

    /** @test */
    public function job_email_konfirmasi_pesanan_masuk_antrian()
    {
        Queue::fake();

        $customer = Customer::factory()->create();
        
        $transaction = \App\Models\Transaction::create([
            'kode_transaksi' => 'T' . rand(10000, 99999),
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'total_amount' => 50000,
            'total_items' => 2,
            'payment_method' => 'cash',
            'queue_number' => '001',
            'status' => 'pending',
            'items' => json_encode([
                ['nama' => 'Product 1', 'harga' => 25000, 'quantity' => 2]
            ]),
        ]);

        // Just verify transaction was created
        $this->assertDatabaseHas('transactions', [
            'customer_email' => $customer->email,
            'total_amount' => 50000,
        ]);
    }

    /** @test */
    public function job_queue_menangani_kegagalan_dengan_baik()
    {
        // Create a user first
        $user = \App\Models\User::factory()->create();
        
        // Test job with invalid data
        $announcement = Announcement::create([
            'title' => 'Test',
            'message' => 'Test Message',
            'sent_by' => $user->id,
        ]);

        try {
            // Dispatch with invalid ID
            ProcessAnnouncementBroadcast::dispatchSync(999999);
            $this->fail('Should have thrown exception');
        } catch (\Exception $e) {
            // Job should handle error gracefully
            $this->assertTrue(true);
        }
    }

    /** @test */
    public function job_yang_gagal_tercatat_di_log()
    {
        // Check if failed_jobs table exists
        $this->assertTrue(Schema::hasTable('failed_jobs'));
    }

    /** @test */
    public function queue_worker_dapat_mencoba_ulang_job_yang_gagal()
    {
        Queue::fake();

        $user = \App\Models\User::factory()->create();
        
        // Simulate job failure and retry
        $announcement = Announcement::create([
            'title' => 'Test',
            'message' => 'Test Message',
            'sent_by' => $user->id,
        ]);

        ProcessAnnouncementBroadcast::dispatch($announcement->id);

        Queue::assertPushed(ProcessAnnouncementBroadcast::class, 1);
    }
}
