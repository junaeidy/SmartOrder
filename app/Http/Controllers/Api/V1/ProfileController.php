<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProfileUpdateRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    /**
     * Get the authenticated customer's profile.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request)
    {
        $customer = $request->user();
        
        return response()->json([
            'success' => true,
            'data' => new CustomerResource($customer)
        ]);
    }
    
    /**
     * Update the authenticated customer's profile.
     *
     * @param  \App\Http\Requests\Api\V1\ProfileUpdateRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(ProfileUpdateRequest $request)
    {
        $customer = $request->user();
        
        $data = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
        ];
        
        // Only update password if provided
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }
        
        $customer->update($data);
        
        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui',
            'data' => new CustomerResource($customer)
        ]);
    }
}
