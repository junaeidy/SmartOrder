<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Transaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode_transaksi',
        'customer_name',
        'customer_email',
        'customer_phone',
        'customer_notes',
        'total_amount',
        'total_items',
        'payment_method',
        'queue_number',
        'status',
        'items',
    ];

    protected $casts = [
        'items' => 'array',
    ];
}