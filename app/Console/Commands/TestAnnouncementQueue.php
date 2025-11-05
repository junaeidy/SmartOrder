<?php

namespace App\Console\Commands;

use App\Models\Announcement;
use App\Models\Customer;
use App\Jobs\ProcessAnnouncementBroadcast;
use Illuminate\Console\Command;

class TestAnnouncementQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'announcement:test {--dry-run : Run without actually dispatching jobs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test announcement queue system';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $dryRun = $this->option('dry-run');

        $this->info('🧪 Testing Announcement Queue System');
        $this->newLine();

        // Check queue connection
        $this->info('1. Checking queue connection...');
        $connection = config('queue.default');
        $this->line("   Queue Connection: {$connection}");
        
        if ($connection === 'sync') {
            $this->warn('   ⚠️  Warning: Queue is set to "sync" - jobs will run synchronously');
            $this->warn('   Set QUEUE_CONNECTION=database in .env for async processing');
        } else {
            $this->info('   ✓ Queue is configured for async processing');
        }
        $this->newLine();

        // Check customers with FCM tokens
        $this->info('2. Checking customers with FCM tokens...');
        $customersWithToken = Customer::whereNotNull('fcm_token')
            ->where('fcm_token', '!=', '')
            ->count();
        $this->line("   Total customers with FCM token: {$customersWithToken}");
        
        if ($customersWithToken === 0) {
            $this->warn('   ⚠️  No customers have FCM tokens - notifications will not be sent');
        } else {
            $this->info("   ✓ Found {$customersWithToken} customers ready to receive notifications");
        }
        $this->newLine();

        // Check announcements
        $this->info('3. Checking announcements...');
        $draftCount = Announcement::where('status', 'draft')->count();
        $sentCount = Announcement::where('status', 'sent')->count();
        $this->line("   Draft announcements: {$draftCount}");
        $this->line("   Sent announcements: {$sentCount}");
        $this->newLine();

        if ($dryRun) {
            $this->info('4. Dry run mode - skipping job dispatch');
            $this->newLine();
            $this->info('✅ Test completed (dry run)');
            return 0;
        }

        // Ask to create test announcement
        if ($this->confirm('Do you want to create and dispatch a test announcement?', false)) {
            $this->info('4. Creating test announcement...');
            
            $announcement = Announcement::create([
                'title' => 'Test Announcement - ' . now()->format('Y-m-d H:i:s'),
                'message' => 'This is a test announcement sent via queue system.',
                'sent_by' => 1, // Default to first user
                'status' => 'draft',
            ]);

            $this->line("   Created announcement ID: {$announcement->id}");
            $this->newLine();

            $this->info('5. Dispatching announcement broadcast job...');
            ProcessAnnouncementBroadcast::dispatch($announcement->id);
            
            $this->info('   ✓ Job dispatched successfully');
            $this->newLine();

            $this->info('📊 Next steps:');
            $this->line('   1. Check jobs table: SELECT * FROM jobs;');
            $this->line('   2. Monitor queue: php artisan queue:monitor database');
            $this->line('   3. Check logs: tail -f storage/logs/laravel.log');
            $this->line('   4. Verify announcement status changes in announcements table');
            $this->newLine();

            $this->info('✅ Test announcement dispatched!');
            
            if ($connection === 'database') {
                $this->warn('⚠️  Don\'t forget to run: php artisan queue:work');
            }
        } else {
            $this->info('Test cancelled.');
        }

        return 0;
    }
}
