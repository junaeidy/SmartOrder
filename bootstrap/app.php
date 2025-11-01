<?php

use App\Http\Middleware\RoleMiddleware;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withSchedule(function (Schedule $schedule) {
        // Reset queue counter every day at midnight
        $schedule->command('queue:reset-counter')->dailyAt('00:00');

        // Cancel expired Midtrans transactions every minute
        $schedule->command('transactions:cancel-expired')
            ->everyMinute()
            ->withoutOverlapping()
            ->appendOutputTo(storage_path('logs/scheduler.log'));

        // Check pending payments every 15 minutes
        $schedule->command('payments:check-pending')->everyFifteenMinutes();

        // Deactivate expired discounts daily
        $schedule->command('discounts:deactivate-expired')->daily();
    })
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // Apply security headers to API routes as well
        $middleware->api(append: [
            \App\Http\Middleware\SecurityHeaders::class,
        ]);

        // Exempt Midtrans webhook endpoints from CSRF verification
        $middleware->validateCsrfTokens(except: [
            '/midtrans/notification',
            '/midtrans/finish',
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'checkout.rate.limit' => \App\Http\Middleware\CheckoutRateLimit::class,
            'rate.limit' => \App\Http\Middleware\ApiRateLimiter::class,
            'device.token' => \App\Http\Middleware\ValidateDeviceToken::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Handle broadcasting exceptions gracefully
        $exceptions->reportable(function (BroadcastException $e) {
            Log::warning('Broadcasting error occurred', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            // Don't throw the exception, just log it
            return false;
        });
        
        // Prevent broadcasting exceptions from being displayed to users
        $exceptions->dontReport([
            BroadcastException::class,
        ]);
    })->create();
