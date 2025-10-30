<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class FcmTokenController extends Controller
{
    /**
     * Save or update FCM token for authenticated user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function store(Request $request)
    {
        $request->validate([
            'fcm_token' => 'required|string',
        ]);

        try {
            $user = Auth::user();
            $user->fcm_token = $request->fcm_token;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'FCM token updated successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update FCM token',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete FCM token for authenticated user (on logout)
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function destroy()
    {
        try {
            $user = Auth::user();
            $user->fcm_token = null;
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'FCM token deleted successfully',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete FCM token',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
