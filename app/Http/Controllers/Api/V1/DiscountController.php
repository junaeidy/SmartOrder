<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DiscountController extends Controller
{
    /**
     * Get all available discounts for customers.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getAvailable(Request $request)
    {
        // Get optional amount parameter for filtering
        $amount = $request->query('amount', 0);

        // Get all active discounts
        $discounts = Discount::where('active', true)
            ->orderBy('percentage', 'desc')
            ->get();

        // Filter valid discounts (checks date and time constraints)
        $validDiscounts = $discounts->filter(function($discount) use ($amount) {
            // Check if discount is currently valid
            if (!$discount->isValid()) {
                return false;
            }
            
            // If amount provided, check minimum purchase
            if ($amount > 0 && $amount < $discount->min_purchase) {
                return false;
            }
            
            return true;
        });

        // Format response
        $formattedDiscounts = $validDiscounts->map(function($discount) use ($amount) {
            $discountAmount = 0;
            if ($amount > 0) {
                $discountAmount = $discount->calculateDiscount($amount);
            }

            return [
                'id' => $discount->id,
                'name' => $discount->name,
                'code' => $discount->code,
                'description' => $discount->description,
                'percentage' => (float) $discount->percentage,
                'min_purchase' => (float) $discount->min_purchase,
                'requires_code' => $discount->requires_code,
                'valid_from' => $discount->valid_from ? $discount->valid_from->toIso8601String() : null,
                'valid_until' => $discount->valid_until ? $discount->valid_until->toIso8601String() : null,
                'time_from' => $discount->time_from,
                'time_until' => $discount->time_until,
                'discount_amount' => $discountAmount,
            ];
        })->values();

        return response()->json([
            'success' => true,
            'data' => $formattedDiscounts,
        ]);
    }

    /**
     * Verify a discount code.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function verifyCode(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'code' => 'required|string',
            'amount' => 'nullable|numeric|min:0',
            'device_id' => 'nullable|string', // Device identifier from mobile app
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Kode diskon tidak valid',
            ], 422);
        }

        $discount = Discount::where('code', $request->code)
            ->where('active', true)
            ->first();
        
        if (!$discount) {
            return response()->json([
                'success' => false,
                'message' => 'Kode diskon tidak ditemukan',
            ], 404);
        }

        if (!$discount->isValid()) {
            // Provide more specific error message
            $errorMessage = $this->getDiscountInvalidReason($discount);
            
            return response()->json([
                'success' => false,
                'message' => $errorMessage,
            ], 400);
        }
        
        // Get customer ID if authenticated
        $customerId = auth('sanctum')->id();
        $deviceId = $request->device_id;
        
        // Check if discount can be used by this customer/device
        if (!$discount->canBeUsedBy($customerId, $deviceId)) {
            return response()->json([
                'success' => false,
                'message' => 'Anda sudah menggunakan kode diskon ini sebelumnya',
            ], 400);
        }

        $amount = $request->amount ?? 0;
        if ($amount < $discount->min_purchase) {
            return response()->json([
                'success' => false,
                'message' => 'Total belanja belum mencapai minimum untuk kode diskon ini (Min. Rp ' . number_format($discount->min_purchase, 0, ',', '.') . ')',
            ], 400);
        }

        $discountAmount = 0;
        if ($amount > 0) {
            $discountAmount = $discount->calculateDiscount($amount);
        }

        return response()->json([
            'success' => true,
            'message' => 'Kode diskon berhasil diterapkan',
            'discount' => [
                'id' => $discount->id,
                'name' => $discount->name,
                'code' => $discount->code,
                'percentage' => $discount->percentage,
                'amount' => $discountAmount,
            ],
        ]);
    }

    /**
     * Get specific reason why discount is invalid.
     *
     * @param  \App\Models\Discount  $discount
     * @return string
     */
    private function getDiscountInvalidReason($discount)
    {
        $now = now();
        
        // Check if discount is inactive
        if (!$discount->active) {
            return 'Kode diskon tidak aktif';
        }
        
        // Check valid_from date
        if ($discount->valid_from && $now->lt($discount->valid_from)) {
            return 'Kode diskon belum dapat digunakan. Berlaku mulai ' . 
                   $discount->valid_from->format('d M Y');
        }
        
        // Check valid_until date
        if ($discount->valid_until && $now->gt($discount->valid_until)) {
            return 'Kode diskon sudah tidak berlaku. Berlaku sampai ' . 
                   $discount->valid_until->format('d M Y');
        }
        
        // Check time constraints
        if ($discount->time_from && $discount->time_until) {
            $currentTime = $now->format('H:i:s');
            $isValidTime = false;
            
            // Handle time range that crosses midnight
            if ($discount->time_from > $discount->time_until) {
                $isValidTime = ($currentTime >= $discount->time_from || $currentTime <= $discount->time_until);
            } else {
                $isValidTime = ($currentTime >= $discount->time_from && $currentTime <= $discount->time_until);
            }
            
            if (!$isValidTime) {
                return 'Kode diskon hanya berlaku pada jam ' . 
                       substr($discount->time_from, 0, 5) . ' - ' . 
                       substr($discount->time_until, 0, 5);
            }
        }
        
        return 'Kode diskon sudah tidak berlaku';
    }
}
