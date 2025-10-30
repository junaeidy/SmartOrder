<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\FirebaseService;

class TestPushNotification extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notification:test {token} {--title=Test} {--body=This is a test notification}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test sending push notification to a specific FCM token';

    /**
     * Execute the console command.
     */
    public function handle(FirebaseService $firebaseService)
    {
        $token = $this->argument('token');
        $title = $this->option('title');
        $body = $this->option('body');

        $this->info('Sending push notification...');
        $this->info("Token: {$token}");
        $this->info("Title: {$title}");
        $this->info("Body: {$body}");

        $result = $firebaseService->sendNotification(
            $token,
            $title,
            $body,
            ['type' => 'test', 'timestamp' => now()->toIso8601String()]
        );

        if ($result) {
            $this->info('✅ Push notification sent successfully!');
            $this->info('Check your mobile device for the notification.');
        } else {
            $this->error('❌ Failed to send push notification.');
            $this->error('Check storage/logs/laravel.log for details.');
        }

        return $result ? 0 : 1;
    }
}
