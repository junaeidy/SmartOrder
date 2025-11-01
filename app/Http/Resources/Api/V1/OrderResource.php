<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kode_transaksi' => $this->kode_transaksi,
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'customer_notes' => $this->customer_notes,
            'total_amount' => $this->total_amount,
            'total_items' => $this->total_items,
            'discount_amount' => $this->discount_amount,
            'tax_amount' => $this->tax_amount,
            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'payment_expires_at' => $this->when($this->payment_expires_at, $this->payment_expires_at?->toIso8601String()),
            'midtrans_payment_url' => $this->when($this->midtrans_payment_url, $this->midtrans_payment_url),
            'queue_number' => $this->queue_number,
            'status' => $this->status,
            'items' => $this->items,
            'paid_at' => $this->paid_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
