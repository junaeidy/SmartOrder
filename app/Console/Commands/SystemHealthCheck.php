<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use App\Models\Transaction;
use App\Models\Customer;
use App\Models\Product;

class SystemHealthCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'monitor:health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check system health and report status';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Running System Health Check...');
        $this->newLine();

        $errors = 0;
        $warnings = 0;

        // 1. Database Connection
        $this->info('1. Database Connection...');
        try {
            DB::connection()->getPdo();
            $this->info('   ✅ Database connected');
        } catch (\Exception $e) {
            $this->error('   ❌ Database connection failed: ' . $e->getMessage());
            $errors++;
        }

        // 2. Cache System
        $this->info('2. Cache System...');
        try {
            Cache::put('health_check', 'test', 60);
            $value = Cache::get('health_check');
            if ($value === 'test') {
                $this->info('   ✅ Cache system working');
                Cache::forget('health_check');
            } else {
                $this->warn('   ⚠️  Cache not returning expected value');
                $warnings++;
            }
        } catch (\Exception $e) {
            $this->error('   ❌ Cache system failed: ' . $e->getMessage());
            $errors++;
        }

        // 3. Database Performance
        $this->info('3. Database Performance...');
        
        // Cache the count for 5 minutes to avoid slow queries on every health check
        $transactionCount = Cache::remember('health_check_transaction_count', 300, function () {
            return Transaction::count();
        });
        
        $this->info("   ✅ Transaction count: {$transactionCount} records (cached)");
        
        // Test a simple indexed query for actual performance
        $start = microtime(true);
        Transaction::where('status', 'completed')->limit(10)->get();
        $queryTime = (microtime(true) - $start) * 1000;
        
        if ($queryTime < 100) {
            $this->info("   ✅ Indexed query performance: {$queryTime}ms");
        } else {
            $this->warn("   ⚠️  Slow indexed query detected: {$queryTime}ms");
            $warnings++;
        }

        // 4. Storage Disk
        $this->info('4. Storage Disk...');
        $storagePath = storage_path();
        if (is_writable($storagePath)) {
            $this->info('   ✅ Storage directory is writable');
        } else {
            $this->error('   ❌ Storage directory is not writable');
            $errors++;
        }

        // 5. Queue System
        $this->info('5. Queue System...');
        try {
            $failedJobs = DB::table('failed_jobs')->count();
            if ($failedJobs > 0) {
                $this->warn("   ⚠️  {$failedJobs} failed jobs in queue");
                $warnings++;
            } else {
                $this->info('   ✅ No failed jobs');
            }
        } catch (\Exception $e) {
            $this->warn('   ⚠️  Could not check queue status');
            $warnings++;
        }

        // 6. Data Integrity
        $this->info('6. Data Integrity...');
        $customerCount = Customer::count();
        $productCount = Product::count();
        $this->info("   📊 Customers: {$customerCount}");
        $this->info("   📊 Products: {$productCount}");
        $this->info("   📊 Transactions: {$transactionCount}");

        // 7. Memory Usage
        $this->info('7. Memory Usage...');
        $memoryUsage = memory_get_usage(true) / 1024 / 1024;
        $memoryLimit = ini_get('memory_limit');
        $this->info("   💾 Current: " . round($memoryUsage, 2) . "MB / Limit: {$memoryLimit}");

        // Summary
        $this->newLine();
        $this->info('=' . str_repeat('=', 50));
        $this->info('Health Check Summary:');
        if ($errors === 0 && $warnings === 0) {
            $this->info('✅ All systems operational');
        } else {
            if ($errors > 0) {
                $this->error("❌ {$errors} critical error(s) detected");
            }
            if ($warnings > 0) {
                $this->warn("⚠️  {$warnings} warning(s) detected");
            }
        }
        $this->info('=' . str_repeat('=', 50));

        return $errors > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
