<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SetupTestDatabase extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:setup';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Setup test database for running tests';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔧 Setting up test database...');
        
        try {
            // Connect to MySQL without database
            $connection = DB::connection()->getPdo();
            
            // Create test database
            $this->info('Creating database: smart_order_test');
            DB::statement('CREATE DATABASE IF NOT EXISTS smart_order_test');
            
            $this->info('✅ Database created successfully!');
            
            // Show databases
            $databases = DB::select('SHOW DATABASES LIKE "smart_order%"');
            
            $this->newLine();
            $this->info('📊 Databases found:');
            foreach ($databases as $db) {
                $dbName = array_values((array)$db)[0];
                if ($dbName === 'smart_order') {
                    $this->line("  - {$dbName} (PRODUCTION) 🔴");
                } else {
                    $this->line("  - {$dbName} (TESTING) 🟢");
                }
            }
            
            $this->newLine();
            $this->info('🎯 Next steps:');
            $this->line('  1. Run migrations for test database:');
            $this->line('     php artisan migrate --env=testing');
            $this->newLine();
            $this->line('  2. Run tests:');
            $this->line('     php artisan test');
            $this->newLine();
            $this->info('✅ Setup complete!');
            
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error('❌ Error: ' . $e->getMessage());
            $this->newLine();
            $this->warn('💡 Cara manual:');
            $this->line('  1. Buka phpMyAdmin atau MySQL client');
            $this->line('  2. Jalankan SQL:');
            $this->line('     CREATE DATABASE smart_order_test;');
            $this->line('  3. Kemudian run: php artisan migrate --env=testing');
            
            return Command::FAILURE;
        }
    }
}
