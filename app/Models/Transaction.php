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
        'payment_status',
        'midtrans_transaction_id',
        'midtrans_transaction_status',
        'midtrans_payment_type',
        'midtrans_payment_url',
        'paid_at',
        'confirmation_email_sent_at',
        'queue_number',
        'status',
        'items',
    ];

    protected $casts = [
        'items' => 'array',
        'paid_at' => 'datetime',
        'confirmation_email_sent_at' => 'datetime',
    ];
}