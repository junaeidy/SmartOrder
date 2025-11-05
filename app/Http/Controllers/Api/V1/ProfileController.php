<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ProfileUpdateRequest;
use App\Http\Requests\Api\V1\ProfileInfoUpdateRequest;
use App\Http\Requests\Api\V1\PasswordUpdateRequest;
use App\Http\Resources\Api\V1\CustomerResource;
use App\Helpers\SecurityHelper;
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
            $data['name'] = SecurityHelper::sanitizeName($request->name);
        }
        
        if ($request->has('phone')) {
            $data['phone'] = SecurityHelper::sanitizePhone($request->phone);
        }
        
        // Handle profile photo upload with comprehensive validation
        if ($request->hasFile('profile_photo')) {
            $file = $request->file('profile_photo');
            
            // Validate file upload
            $validationError = $this->validateProfilePhoto($file);
            if ($validationError) {
                return response()->json([
                    'success' => false,
                    'message' => $validationError
                ], 422);
            }
            
            // Delete old photo if exists
            if ($customer->profile_photo) {
                Storage::disk('public')->delete($customer->profile_photo);
            }
            
            // Store new photo with sanitized filename
            $extension = $file->getClientOriginalExtension();
            $filename = 'profile_' . $customer->id . '_' . time() . '.' . $extension;
            $path = $file->storeAs('profile-photos', $filename, 'public');
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
     * Validate profile photo upload
     *
     * @param  \Illuminate\Http\UploadedFile  $file
     * @return string|null Error message or null if valid
     */
    private function validateProfilePhoto($file): ?string
    {
        // Check if file is actually uploaded
        if (!$file->isValid()) {
            return 'File upload gagal. Silakan coba lagi.';
        }
        
        // Validate file size (max 2MB)
        $maxSize = 2 * 1024 * 1024; // 2MB in bytes
        if ($file->getSize() > $maxSize) {
            return 'Ukuran file terlalu besar. Maksimal 2MB.';
        }
        
        // Validate MIME type (only images)
        $allowedMimes = ['image/jpeg', 'image/png', 'image/jpg', 'image/gif', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimes)) {
            return 'Format file tidak valid. Hanya JPEG, PNG, GIF, dan WebP yang diperbolehkan.';
        }
        
        // Validate file extension
        $allowedExtensions = ['jpeg', 'jpg', 'png', 'gif', 'webp'];
        $extension = strtolower($file->getClientOriginalExtension());
        if (!in_array($extension, $allowedExtensions)) {
            return 'Ekstensi file tidak valid.';
        }
        
        // Additional security: Check if file is actually an image
        try {
            $imageInfo = @getimagesize($file->getRealPath());
            if ($imageInfo === false) {
                return 'File bukan gambar yang valid.';
            }
            
            // Validate image dimensions (optional - prevent extremely large images)
            list($width, $height) = $imageInfo;
            $maxDimension = 4096; // Max 4096px on any side
            if ($width > $maxDimension || $height > $maxDimension) {
                return 'Dimensi gambar terlalu besar. Maksimal 4096x4096 pixels.';
            }
        } catch (\Exception $e) {
            return 'Gagal memvalidasi gambar.';
        }
        
        return null; // Valid
    }
    
    /**
     * Update the authenticated customer's password.
```
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
