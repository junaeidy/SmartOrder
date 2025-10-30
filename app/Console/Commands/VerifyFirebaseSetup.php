<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FirebaseService;

class VerifyFirebaseSetup extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'firebase:verify';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Verify Firebase Cloud Messaging setup and configuration';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🔍 Verifying Firebase Configuration...');
        $this->newLine();

        $allPassed = true;

        // Check 1: Config file exists
        $this->info('1. Checking config file...');
        if (file_exists(config_path('firebase.php'))) {
            $this->info('   ✅ Config file exists: config/firebase.php');
        } else {
            $this->error('   ❌ Config file not found: config/firebase.php');
            $allPassed = false;
        }
        $this->newLine();

        // Check 2: Environment variables
        $this->info('2. Checking environment variables...');
        
        $credentialsPath = config('firebase.credentials.file');
        if ($credentialsPath) {
            $this->info("   ✅ FIREBASE_CREDENTIALS configured: {$credentialsPath}");
        } else {
            $this->error('   ❌ FIREBASE_CREDENTIALS not set in .env');
            $allPassed = false;
        }

        $projectId = config('firebase.project_id');
        if ($projectId) {
            $this->info("   ✅ FIREBASE_PROJECT_ID configured: {$projectId}");
        } else {
            $this->warn('   ⚠️  FIREBASE_PROJECT_ID not set (optional but recommended)');
        }

        $fcmEnabled = config('firebase.fcm.enabled');
        $this->info('   ✅ FCM_ENABLED: ' . ($fcmEnabled ? 'true' : 'false'));
        $this->newLine();

        // Check 3: Service account file
        $this->info('3. Checking service account file...');
        
        // Try relative path first
        $relativePath = $credentialsPath;
        $absolutePath = base_path($credentialsPath);
        
        $this->info("   Relative path: {$relativePath}");
        $this->info("   Absolute path: {$absolutePath}");
        $this->info("   Base path: " . base_path());
        $this->info("   Storage path: " . storage_path());
        
        if (file_exists($absolutePath)) {
            $this->info("   ✅ Service account file found at: {$absolutePath}");
            
            // Check file permissions
            $perms = substr(sprintf('%o', fileperms($absolutePath)), -4);
            $this->info("   File permissions: {$perms}");
            
            if (is_readable($absolutePath)) {
                $this->info('   ✅ File is readable');
                
                // Try to read and validate JSON
                try {
                    $content = file_get_contents($absolutePath);
                    $json = json_decode($content, true);
                    
                    if ($json && isset($json['type']) && $json['type'] === 'service_account') {
                        $this->info('   ✅ Valid service account JSON file');
                        
                        if (isset($json['project_id'])) {
                            $this->info("   Project ID in file: {$json['project_id']}");
                        }
                        
                        if (isset($json['client_email'])) {
                            $this->info("   Service account email: {$json['client_email']}");
                        }
                    } else {
                        $this->error('   ❌ Invalid service account JSON format');
                        $allPassed = false;
                    }
                } catch (\Exception $e) {
                    $this->error("   ❌ Error reading file: {$e->getMessage()}");
                    $allPassed = false;
                }
            } else {
                $this->error('   ❌ File is not readable. Check permissions.');
                $allPassed = false;
            }
        } else {
            $this->error("   ❌ Service account file NOT found at: {$absolutePath}");
            $this->error('   Please upload the file from Firebase Console');
            $allPassed = false;
        }
        $this->newLine();

        // Check 4: Firebase Service initialization
        $this->info('4. Testing Firebase Service initialization...');
        try {
            $firebaseService = app(FirebaseService::class);
            $this->info('   ✅ FirebaseService instantiated successfully');
            
            // Check if messaging is initialized
            $reflection = new \ReflectionClass($firebaseService);
            $messagingProperty = $reflection->getProperty('messaging');
            $messagingProperty->setAccessible(true);
            $messaging = $messagingProperty->getValue($firebaseService);
            
            if ($messaging !== null) {
                $this->info('   ✅ Firebase Messaging initialized successfully');
            } else {
                $this->error('   ❌ Firebase Messaging is NULL (initialization failed)');
                $this->error('   Check logs: tail -f storage/logs/laravel.log');
                $allPassed = false;
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Error initializing FirebaseService: {$e->getMessage()}");
            $allPassed = false;
        }
        $this->newLine();

        // Check 5: Database migrations
        $this->info('5. Checking database schema...');
        try {
            $hasUsersFcmToken = \Schema::hasColumn('users', 'fcm_token');
            $hasCustomersFcmToken = \Schema::hasColumn('customers', 'fcm_token');
            
            if ($hasUsersFcmToken) {
                $this->info('   ✅ users.fcm_token column exists');
            } else {
                $this->error('   ❌ users.fcm_token column NOT found. Run migrations!');
                $allPassed = false;
            }
            
            if ($hasCustomersFcmToken) {
                $this->info('   ✅ customers.fcm_token column exists');
            } else {
                $this->error('   ❌ customers.fcm_token column NOT found. Run migrations!');
                $allPassed = false;
            }
        } catch (\Exception $e) {
            $this->error("   ❌ Error checking database: {$e->getMessage()}");
            $allPassed = false;
        }
        $this->newLine();

        // Check 6: Routes
        $this->info('6. Checking API routes...');
        $routes = \Route::getRoutes();
        
        $hasSaveRoute = false;
        $hasDeleteRoute = false;
        
        foreach ($routes as $route) {
            if ($route->uri() === 'api/v1/user/fcm-token' && in_array('POST', $route->methods())) {
                $hasSaveRoute = true;
            }
            if ($route->uri() === 'api/v1/user/fcm-token/delete' && in_array('DELETE', $route->methods())) {
                $hasDeleteRoute = true;
            }
        }
        
        if ($hasSaveRoute) {
            $this->info('   ✅ POST /api/v1/user/fcm-token route registered');
        } else {
            $this->error('   ❌ Save FCM token route NOT found');
            $allPassed = false;
        }
        
        if ($hasDeleteRoute) {
            $this->info('   ✅ DELETE /api/v1/user/fcm-token/delete route registered');
        } else {
            $this->error('   ❌ Delete FCM token route NOT found');
            $allPassed = false;
        }
        $this->newLine();

        // Check 7: Event Listener
        $this->info('7. Checking Event Listener...');
        $listeners = \Event::getListeners(\App\Events\OrderStatusChanged::class);
        
        if (!empty($listeners)) {
            $this->info('   ✅ OrderStatusChanged event has listeners registered');
            foreach ($listeners as $listener) {
                if (is_string($listener)) {
                    $this->info("   - {$listener}");
                } else {
                    $this->info('   - ' . get_class($listener));
                }
            }
        } else {
            $this->warn('   ⚠️  No listeners registered for OrderStatusChanged event');
            $this->warn('   Check app/Providers/EventServiceProvider.php');
        }
        $this->newLine();

        // Final summary
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        if ($allPassed) {
            $this->info('✅ All checks PASSED! Firebase is ready to use.');
            $this->newLine();
            $this->info('Next steps:');
            $this->info('1. Test notification: php artisan notification:test {fcm-token}');
            $this->info('2. Monitor logs: tail -f storage/logs/laravel.log');
        } else {
            $this->error('❌ Some checks FAILED. Please fix the issues above.');
            $this->newLine();
            $this->info('Quick fixes:');
            $this->info('1. Upload service-account.json to storage/app/firebase/');
            $this->info('2. Update .env with correct FIREBASE_CREDENTIALS path');
            $this->info('3. Run: php artisan migrate');
            $this->info('4. Run: php artisan config:clear');
            $this->info('5. Check logs: tail -f storage/logs/laravel.log');
        }
        $this->info('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        return $allPassed ? 0 : 1;
    }
}
