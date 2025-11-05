<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Prevent clickjacking attacks
        $response->headers->set('X-Frame-Options', 'DENY');
        
        // Prevent MIME type sniffing
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        
        // Control referrer information
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        
        // XSS Protection (legacy but still useful for older browsers)
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        
        // HTTP Strict Transport Security (HSTS) - Only in production with HTTPS
        if (app()->environment('production') && $request->secure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains; preload');
        }
        
        // Content Security Policy
        $csp = $this->getContentSecurityPolicy($request);
        $response->headers->set('Content-Security-Policy', $csp);
        
        // Permissions Policy (formerly Feature-Policy)
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), payment=()');
        
        // Prevent browser from caching sensitive data
        if ($this->isSensitiveRoute($request)) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
    
    /**
     * Get Content Security Policy based on environment
     */
    private function getContentSecurityPolicy(Request $request): string
    {
        $policies = [
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval'", // unsafe-eval needed for Vite/React
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net",
            "style-src-elem 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net",
            "font-src 'self' https://fonts.gstatic.com https://fonts.bunny.net data:",
            "img-src 'self' data: https: blob:",
            "connect-src 'self' https://api.midtrans.com https://app.midtrans.com https://api-ap1.pusher.com wss://ws-ap1.pusher.com https://fcm.googleapis.com",
            "frame-src https://api.midtrans.com https://app.midtrans.com",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "upgrade-insecure-requests",
        ];
        
        // In development, relax some policies
        if (app()->environment('local')) {
            $policies[] = "script-src 'self' 'unsafe-inline' 'unsafe-eval' http://localhost:* ws://localhost:*";
            $policies[] = "connect-src 'self' http://localhost:* ws://localhost:* wss://localhost:*";
        }
        
        return implode('; ', $policies);
    }
    
    /**
     * Check if route contains sensitive data
     */
    private function isSensitiveRoute(Request $request): bool
    {
        $sensitivePaths = [
            'api/v1/profile',
            'api/v1/checkout',
            'api/v1/payment',
            'api/v1/orders',
            'login',
            'register',
        ];
        
        foreach ($sensitivePaths as $path) {
            if ($request->is($path) || $request->is($path . '/*')) {
                return true;
            }
        }
        
        return false;
    }
}
