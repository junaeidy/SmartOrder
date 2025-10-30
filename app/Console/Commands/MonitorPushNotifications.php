<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MonitorPushNotifications extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:monitor {--hours=1 : Number of hours to check}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor push notifications from logs to detect duplicates';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = $this->option('hours');
        $logFile = storage_path('logs/laravel.log');

        if (!file_exists($logFile)) {
            $this->error('Log file not found: ' . $logFile);
            return 1;
        }

        $this->info("📊 Analyzing push notifications from last {$hours} hour(s)...");
        $this->newLine();

        // Read log file
        $content = file_get_contents($logFile);
        
        // Find all push notification entries
        preg_match_all(
            '/\[(.*?)\].*?Order status push notification sent.*?"transaction_id":(\d+).*?"status":"(.*?)".*?"customer_id":(\d+)/',
            $content,
            $matches,
            PREG_SET_ORDER
        );

        if (empty($matches)) {
            $this->warn('No push notifications found in logs');
            return 0;
        }

        // Group by transaction + status
        $notifications = [];
        $cutoffTime = now()->subHours($hours);

        foreach ($matches as $match) {
            $timestamp = \Carbon\Carbon::parse($match[1]);
            
            if ($timestamp->lt($cutoffTime)) {
                continue;
            }

            $key = "tx_{$match[2]}_status_{$match[3]}";
            
            if (!isset($notifications[$key])) {
                $notifications[$key] = [
                    'transaction_id' => $match[2],
                    'status' => $match[3],
                    'customer_id' => $match[4],
                    'count' => 0,
                    'timestamps' => [],
                ];
            }
            
            $notifications[$key]['count']++;
            $notifications[$key]['timestamps'][] = $timestamp->format('Y-m-d H:i:s');
        }

        // Find duplicates
        $duplicates = array_filter($notifications, function($n) {
            return $n['count'] > 1;
        });

        // Display results
        $this->info("Total notifications: " . count($matches));
        $this->info("Unique notification types: " . count($notifications));
        $this->newLine();

        if (empty($duplicates)) {
            $this->info('✅ No duplicate notifications found!');
        } else {
            $this->error('⚠️  Found ' . count($duplicates) . ' duplicate notification(s):');
            $this->newLine();

            $headers = ['Transaction ID', 'Status', 'Customer ID', 'Count', 'Timestamps'];
            $rows = [];

            foreach ($duplicates as $dup) {
                $rows[] = [
                    $dup['transaction_id'],
                    $dup['status'],
                    $dup['customer_id'],
                    $dup['count'],
                    implode("\n", $dup['timestamps']),
                ];
            }

            $this->table($headers, $rows);
        }

        // Show summary
        $this->newLine();
        $this->info('📈 Summary:');
        $this->table(
            ['Metric', 'Value'],
            [
                ['Total Sent', count($matches)],
                ['Unique Types', count($notifications)],
                ['Duplicates', count($duplicates)],
                ['Duplicate Rate', count($matches) > 0 ? round((count($duplicates) / count($matches)) * 100, 2) . '%' : '0%'],
            ]
        );

        return empty($duplicates) ? 0 : 1;
    }
}
