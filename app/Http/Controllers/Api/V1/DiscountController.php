<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Discount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class DiscountController extends Controller
{
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
            return response()->json([
                'success' => false,
                'message' => 'Kode diskon sudah tidak berlaku',
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
                'name' => $discount->name,
                'code' => $discount->code,
                'percentage' => $discount->percentage,
                'amount' => $discountAmount,
            ],
        ]);
    }
}
