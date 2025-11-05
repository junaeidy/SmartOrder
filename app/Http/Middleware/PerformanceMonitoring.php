<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitoring
{
    /**
     * Handle an incoming request and log performance metrics.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        // Process request
        $response = $next($request);

        // Calculate metrics
        $endTime = microtime(true);
        $endMemory = memory_get_usage();
        $executionTime = ($endTime - $startTime) * 1000; // Convert to milliseconds
        $memoryUsed = ($endMemory - $startMemory) / 1024 / 1024; // Convert to MB

        // Log slow requests (over 500ms)
        if ($executionTime > 500) {
            Log::warning('Slow API Request', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'execution_time' => round($executionTime, 2) . 'ms',
                'memory_used' => round($memoryUsed, 2) . 'MB',
                'user_agent' => $request->userAgent(),
                'ip' => $request->ip(),
            ]);
        }

        // Add performance headers (useful for debugging)
        $response->headers->set('X-Execution-Time', round($executionTime, 2) . 'ms');
        $response->headers->set('X-Memory-Used', round($memoryUsed, 2) . 'MB');

        return $response;
    }
}
