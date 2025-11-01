<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimiter
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $limiterName = 'api'): Response
    {
        $key = $this->resolveRequestSignature($request, $limiterName);

        $limiter = $this->getLimiterConfig($limiterName);

        if (RateLimiter::tooManyAttempts($key, $limiter['max_attempts'])) {
            return $this->buildTooManyAttemptsResponse($key, $limiter['max_attempts']);
        }

        RateLimiter::hit($key, $limiter['decay_seconds']);

        $response = $next($request);

        return $this->addHeaders(
            $response,
            $limiter['max_attempts'],
            $this->calculateRemainingAttempts($key, $limiter['max_attempts'])
        );
    }

    /**
     * Resolve request signature.
     */
    protected function resolveRequestSignature(Request $request, string $limiterName): string
    {
        if ($user = $request->user()) {
            return sha1($limiterName . '|' . $user->id);
        }

        return sha1($limiterName . '|' . $request->ip());
    }

    /**
     * Get limiter configuration.
     */
    protected function getLimiterConfig(string $limiterName): array
    {
        $limiters = [
            // Authentication endpoints - Prevent brute force attacks
            'auth.login' => [
                'max_attempts' => 5,      // 5 attempts
                'decay_seconds' => 300,   // per 5 minutes
            ],
            'auth.register' => [
                'max_attempts' => 3,      // 3 registrations
                'decay_seconds' => 3600,  // per hour
            ],
            'auth.password.reset' => [
                'max_attempts' => 3,      // 3 attempts
                'decay_seconds' => 600,   // per 10 minutes
            ],

            // Payment endpoints - Prevent abuse and DDoS
            'payment.checkout' => [
                'max_attempts' => 10,     // 10 checkouts
                'decay_seconds' => 60,    // per minute
            ],
            'payment.webhook' => [
                'max_attempts' => 100,    // 100 webhooks
                'decay_seconds' => 60,    // per minute (from payment gateway)
            ],

            // Write operations - Prevent spam
            'write.intensive' => [
                'max_attempts' => 20,     // 20 writes
                'decay_seconds' => 60,    // per minute
            ],

            // Discount verification - Prevent coupon farming
            'discount.verify' => [
                'max_attempts' => 10,     // 10 verifications
                'decay_seconds' => 60,    // per minute
            ],

            // Default API rate limit
            'api' => [
                'max_attempts' => 60,     // 60 requests
                'decay_seconds' => 60,    // per minute
            ],

            // Heavy read operations
            'read.heavy' => [
                'max_attempts' => 30,     // 30 requests
                'decay_seconds' => 60,    // per minute
            ],
        ];

        return $limiters[$limiterName] ?? $limiters['api'];
    }

    /**
     * Calculate the number of remaining attempts.
     */
    protected function calculateRemainingAttempts(string $key, int $maxAttempts): int
    {
        return RateLimiter::remaining($key, $maxAttempts);
    }

    /**
     * Add the limit header information to the response.
     */
    protected function addHeaders(Response $response, int $maxAttempts, int $remainingAttempts): Response
    {
        $response->headers->add([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $remainingAttempts),
        ]);

        return $response;
    }

    /**
     * Create a 'too many attempts' response.
     */
    protected function buildTooManyAttemptsResponse(string $key, int $maxAttempts): Response
    {
        $retryAfter = RateLimiter::availableIn($key);

        return response()->json([
            'success' => false,
            'message' => 'Terlalu banyak request. Silakan coba lagi nanti.',
            'errors' => [
                'rate_limit' => [
                    'Anda telah mencapai batas maksimal request.',
                    'Silakan tunggu ' . $this->formatRetryAfter($retryAfter) . ' sebelum mencoba lagi.'
                ]
            ],
            'retry_after' => $retryAfter,
        ], 429, [
            'Retry-After' => $retryAfter,
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => 0,
        ]);
    }

    /**
     * Format retry after time in human readable format.
     */
    protected function formatRetryAfter(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . ' detik';
        }

        $minutes = ceil($seconds / 60);
        return $minutes . ' menit';
    }
}
