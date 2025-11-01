<?php

namespace App\Http\Middleware;

use App\Services\DeviceTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateDeviceToken
{
    protected $deviceTokenService;

    public function __construct(DeviceTokenService $deviceTokenService)
    {
        $this->deviceTokenService = $deviceTokenService;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip validation if user is not authenticated
        if (!$request->user()) {
            return $next($request);
        }

        // Get device_id from request header or body
        $deviceId = $request->header('X-Device-ID') ?? $request->input('device_id');

        // If no device_id provided, reject request
        if (!$deviceId) {
            return response()->json([
                'success' => false,
                'message' => 'Device ID tidak ditemukan.',
                'errors' => [
                    'device_id' => ['Device ID diperlukan untuk mengakses API.']
                ],
            ], 401);
        }

        $customer = $request->user();

        // Check if device is authorized
        if (!$this->deviceTokenService->isDeviceAuthorized($customer, $deviceId)) {
            return response()->json([
                'success' => false,
                'message' => 'Device tidak diizinkan.',
                'errors' => [
                    'device' => [
                        'Akun Anda telah login di perangkat lain.',
                        'Silakan login kembali untuk menggunakan perangkat ini.'
                    ]
                ],
                'error_code' => 'DEVICE_UNAUTHORIZED'
            ], 401);
        }

        return $next($request);
    }
}
