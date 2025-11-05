<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use App\Helpers\SecurityHelper;

class VerifyWebhookApiKey
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $providedKey = $request->header('X-API-Key');
        $expectedKey = config('app.webhook_api_key');
        
        // If no API key is configured, log warning and allow (for backward compatibility)
        if (empty($expectedKey)) {
            Log::warning('Webhook API key not configured. Please set WEBHOOK_API_KEY in .env');
            return $next($request);
        }
        
        // Verify API key using timing-attack safe comparison
        if (!SecurityHelper::verifyApiKey($providedKey, $expectedKey)) {
            Log::warning('Invalid webhook API key attempt', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'path' => $request->path(),
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }
        
        return $next($request);
    }
}
