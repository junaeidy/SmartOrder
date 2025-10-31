<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\RegisterRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
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

        // Update FCM token if provided during login
        if ($request->filled('fcm_token')) {
            $customer->fcm_token = $request->fcm_token;
            $customer->save();
            
            Log::info('FCM token updated during login', [
                'customer_id' => $customer->id,
                'token_preview' => substr($request->fcm_token, 0, 20) . '...',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Login berhasil',
            'data' => [
                'customer' => new CustomerResource($customer),
                'token' => $customer->createToken('auth_token')->plainTextToken,
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
        
        // Clear FCM token on logout
        $customer->fcm_token = null;
        $customer->save();
        
        Log::info('Customer logged out and FCM token cleared', [
            'customer_id' => $customer->id,
        ]);
        
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'success' => true,
            'message' => 'Logout berhasil',
        ]);
    }
}
