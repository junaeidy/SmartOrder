<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MonitorDatabasePerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:database';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Monitor database performance and log slow queries';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // Enable query logging
        DB::enableQueryLog();

        $this->info('Database monitoring started...');
        $this->info('Press Ctrl+C to stop monitoring');

        // Monitor for 60 seconds
        $endTime = time() + 60;

        while (time() < $endTime) {
            $queries = DB::getQueryLog();

            foreach ($queries as $query) {
                $time = $query['time']; // in milliseconds

                if ($time > 100) { // Log queries taking more than 100ms
                    Log::warning('Slow Database Query', [
                        'query' => $query['query'],
                        'bindings' => $query['bindings'],
                        'time' => $time . 'ms'
                    ]);

                    $this->warn("Slow query detected: {$time}ms");
                    $this->line("SQL: " . $query['query']);
                }
            }

            DB::flushQueryLog();
            sleep(5); // Check every 5 seconds
        }

        $this->info('Monitoring completed.');
        return Command::SUCCESS;
    }
}
