<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class CheckoutRateLimit
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        
        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication required'
            ], 401);
        }

        $key = 'checkout_attempts:' . $user->email;
        
        // Allow 3 checkout attempts per 5 minutes per customer
        $executed = RateLimiter::attempt(
            $key,
            $maxAttempts = 3,
            function () {},
            $decayMinutes = 5
        );

        if (!$executed) {
            $availableIn = RateLimiter::availableIn($key);
            
            return response()->json([
                'success' => false,
                'message' => 'Terlalu banyak percobaan checkout. Silakan tunggu ' . ceil($availableIn / 60) . ' menit.',
                'error_code' => 'RATE_LIMITED',
                'data' => [
                    'retry_after_seconds' => $availableIn,
                    'retry_after_minutes' => ceil($availableIn / 60)
                ]
            ], 429); // 429 Too Many Requests
        }

        return $next($request);
    }
}
