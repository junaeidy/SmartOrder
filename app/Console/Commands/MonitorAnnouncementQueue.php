<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonitorAnnouncementQueue extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'announcement:monitor {--refresh=5 : Refresh interval in seconds}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor announcement queue jobs in real-time';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $refresh = (int) $this->option('refresh');

        $this->info('📊 Monitoring Announcement Queue Jobs');
        $this->info('Press Ctrl+C to stop');
        $this->newLine();

        while (true) {
            $this->displayStats();
            sleep($refresh);
            
            // Clear screen (works on most terminals)
            if (PHP_OS_FAMILY !== 'Windows') {
                system('clear');
            }
        }

        return 0;
    }

    protected function displayStats()
    {
        $this->line('Last updated: ' . now()->format('Y-m-d H:i:s'));
        $this->newLine();

        // Jobs in queue
        $pendingJobs = DB::table('jobs')
            ->where('queue', 'default')
            ->count();

        $broadcastJobs = DB::table('jobs')
            ->where('payload', 'like', '%ProcessAnnouncementBroadcast%')
            ->count();

        $notificationJobs = DB::table('jobs')
            ->where('payload', 'like', '%SendAnnouncementNotification%')
            ->count();

        $this->info('📋 Queue Status:');
        $this->table(
            ['Type', 'Count'],
            [
                ['Total Pending Jobs', $pendingJobs],
                ['Broadcast Jobs', $broadcastJobs],
                ['Notification Jobs', $notificationJobs],
            ]
        );

        // Failed jobs
        $failedJobs = DB::table('failed_jobs')
            ->where('created_at', '>=', now()->subHour())
            ->count();

        $this->newLine();
        $this->info('❌ Failed Jobs (last 1 hour):');
        $this->line("   Total: {$failedJobs}");

        if ($failedJobs > 0) {
            $recentFailed = DB::table('failed_jobs')
                ->where('created_at', '>=', now()->subHour())
                ->orderBy('failed_at', 'desc')
                ->limit(5)
                ->get(['id', 'connection', 'queue', 'failed_at']);

            $this->newLine();
            $this->warn('Recent failures:');
            $this->table(
                ['ID', 'Connection', 'Queue', 'Failed At'],
                $recentFailed->map(fn($job) => [
                    $job->id,
                    $job->connection,
                    $job->queue,
                    $job->failed_at,
                ])->toArray()
            );
        }

        // Announcements status
        $this->newLine();
        $this->info('📢 Announcements Status:');
        
        $statuses = DB::table('announcements')
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get();

        $this->table(
            ['Status', 'Count'],
            $statuses->map(fn($s) => [$s->status, $s->count])->toArray()
        );

        // Recent announcements
        $this->newLine();
        $this->info('📝 Recent Announcements (last 10):');
        
        $recent = DB::table('announcements')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get(['id', 'title', 'status', 'recipients_count', 'sent_at']);

        $this->table(
            ['ID', 'Title', 'Status', 'Recipients', 'Sent At'],
            $recent->map(fn($a) => [
                $a->id,
                substr($a->title, 0, 30) . (strlen($a->title) > 30 ? '...' : ''),
                $a->status,
                $a->recipients_count ?? '-',
                $a->sent_at ? date('Y-m-d H:i', strtotime($a->sent_at)) : '-',
            ])->toArray()
        );

        $this->newLine();
        $this->line('Refreshing in ' . $this->option('refresh') . ' seconds...');
    }
}
