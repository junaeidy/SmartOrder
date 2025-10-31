<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProfileUpdateRequest;
use App\Http\Requests\Api\V1\ProfileInfoUpdateRequest;
use App\Http\Requests\Api\V1\PasswordUpdateRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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
     * Update the authenticated customer's profile information (name, email, phone, profile_photo).
     *
     * @param  \App\Http\Requests\Api\V1\ProfileInfoUpdateRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updateInfo(ProfileInfoUpdateRequest $request)
    {
        $customer = $request->user();
        
        $data = [];
        
        // Only update fields that are provided
        if ($request->has('name')) {
            $data['name'] = $request->name;
        }
        
        if ($request->has('phone')) {
            $data['phone'] = $request->phone;
        }
        
        // Handle profile photo upload
        if ($request->hasFile('profile_photo')) {
            // Delete old photo if exists
            if ($customer->profile_photo) {
                Storage::disk('public')->delete($customer->profile_photo);
            }
            
            // Store new photo
            $path = $request->file('profile_photo')->store('profile-photos', 'public');
            $data['profile_photo'] = $path;
        }
        
        // Only update if there's data to update
        if (!empty($data)) {
            $customer->update($data);
        }
        
        return response()->json([
            'success' => true,
            'message' => 'Profil berhasil diperbarui',
            'data' => new CustomerResource($customer)
        ]);
    }
    
    /**
     * Update the authenticated customer's password.
     *
     * @param  \App\Http\Requests\Api\V1\PasswordUpdateRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function updatePassword(PasswordUpdateRequest $request)
    {
        $customer = $request->user();
        
        // Verify current password
        if (!Hash::check($request->current_password, $customer->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['Password lama tidak cocok'],
            ]);
        }
        
        // Update password
        $customer->update([
            'password' => Hash::make($request->new_password)
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Password berhasil diperbarui',
        ]);
    }
    
    /**
     * Update the authenticated customer's profile (Legacy - for backward compatibility).
     *
     * @param  \App\Http\Requests\Api\V1\ProfileUpdateRequest  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(ProfileUpdateRequest $request)
    {
        $customer = $request->user();
        
        $data = [
            'name' => $request->name,
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
