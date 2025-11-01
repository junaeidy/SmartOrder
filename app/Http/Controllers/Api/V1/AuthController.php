<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Models\Customer;
use App\Services\DeviceTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    protected $deviceTokenService;

    public function __construct(DeviceTokenService $deviceTokenService)
    {
        $this->deviceTokenService = $deviceTokenService;
    }

    /**
     * Register a new customer.
     *
     * @param  \App\Http\Requests\Api\V1\RegisterRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function register(RegisterRequest $request)
    {
        $customer = Customer::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => Hash::make($request->password),
            'fcm_token' => $request->fcm_token, // Save FCM token during registration
        ]);

        $token = $customer->createToken('auth_token')->plainTextToken;

        // Register device if device_id is provided
        if ($request->filled('device_id')) {
            $this->deviceTokenService->registerDevice(
                $customer,
                $request->device_id,
                $request->device_name,
                $request->device_type,
                $token
            );

            Log::info('Device registered during customer registration', [
                'customer_id' => $customer->id,
                'device_name' => $request->device_name,
            ]);
        }

        if ($request->filled('fcm_token')) {
            Log::info('FCM token saved during registration', [
                'customer_id' => $customer->id,
                'token_preview' => substr($request->fcm_token, 0, 20) . '...',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Registrasi berhasil',
            'data' => [
                'customer' => new CustomerResource($customer),
                'token' => $token,
            ]
        ], 201);
    }

    /**
     * Login customer.
     *
     * @param  \App\Http\Requests\Api\V1\LoginRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function login(LoginRequest $request)
    {
        $customer = Customer::where('email', $request->email)->first();

        if (! $customer || ! Hash::check($request->password, $customer->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        // Create new token
        $token = $customer->createToken('auth_token')->plainTextToken;

        // Update FCM token if provided during login
        if ($request->filled('fcm_token')) {
            $customer->fcm_token = $request->fcm_token;
            $customer->save();
            
            Log::info('FCM token updated during login', [
                'customer_id' => $customer->id,
                'token_preview' => substr($request->fcm_token, 0, 20) . '...',
            ]);
        }

        // Handle device binding
        if ($request->filled('device_id')) {
            // Register this device
            $this->deviceTokenService->registerDevice(
                $customer,
                $request->device_id,
                $request->device_name,
                $request->device_type,
                $token
            );

            // Revoke other devices for security (single device login)
            $revokedCount = $this->deviceTokenService->revokeOtherDevices(
                $customer,
                $request->device_id
            );

            if ($revokedCount > 0) {
                Log::info('Other devices revoked on login', [
                    'customer_id' => $customer->id,
                    'revoked_count' => $revokedCount,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'customer' => new CustomerResource($customer),
                'token' => $token,
            ]
        ]);
    }

    /**
     * Logout customer.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        $customer = $request->user();
        
        // Revoke device token if device_id is provided
        if ($request->filled('device_id')) {
            $this->deviceTokenService->revokeDevice($customer, $request->device_id);
            
            Log::info('Device token revoked on logout', [
                'customer_id' => $customer->id,
            ]);
        }
        
        // Clear FCM token on logout
        $customer->fcm_token = null;
        $customer->save();
        
        Log::info('Customer logged out and FCM token cleared', [
            'customer_id' => $customer->id,
        ]);
        
        // Delete current access token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil',
        ]);
    }

    /**
     * Get active devices for current customer.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDevices(Request $request)
    {
        $customer = $request->user();
        $devices = $this->deviceTokenService->getActiveDevices($customer);

        return response()->json([
            'success' => true,
            'data' => $devices->map(function ($device) {
                return [
                    'id' => $device->id,
                    'device_name' => $device->device_name,
                    'device_type' => $device->device_type,
                    'last_used_at' => $device->last_used_at,
                    'created_at' => $device->created_at,
                ];
            }),
        ]);
    }

    /**
     * Revoke all other devices.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function revokeOtherDevices(Request $request)
    {
        $request->validate([
            'device_id' => 'required|string',
        ]);

        $customer = $request->user();
        $revokedCount = $this->deviceTokenService->revokeOtherDevices(
            $customer,
            $request->device_id
        );

        return response()->json([
            'success' => true,
            'message' => "{$revokedCount} perangkat lain telah dicabut aksesnya",
            'revoked_count' => $revokedCount,
        ]);
    }
}
