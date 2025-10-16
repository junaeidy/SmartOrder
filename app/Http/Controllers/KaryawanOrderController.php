<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KaryawanOrderController extends Controller
{
    public function index()
    {
        $today = now()->startOfDay();
        $todayEnd = now()->endOfDay();

        return Inertia::render('Karyawan/Orders', [
            'pendingOrders' => Transaction::where('status', 'waiting')
                ->orderBy('queue_number', 'asc') // Changed to ascending for FIFO order
                ->get()
                ->map(function($transaction) {
                    return [
                        'id' => $transaction->id,
                        'kode_transaksi' => $transaction->kode_transaksi,
                        'customer_name' => $transaction->customer_name,
                        'customer_phone' => $transaction->customer_phone,
                        'customer_notes' => $transaction->customer_notes,
                        'total_amount' => $transaction->total_amount,
                        'total_items' => $transaction->total_items,
                        'queue_number' => $transaction->queue_number,
                        'status' => $transaction->status,
                        'created_at' => $transaction->created_at,
                        'updated_at' => $transaction->updated_at,
                        'items' => $transaction->items,
                    ];
                }),
            'completedOrders' => Transaction::where('status', 'completed')
                ->whereBetween('created_at', [$today, $todayEnd])
                ->orderBy('updated_at', 'desc')
                ->get()
                ->map(function($transaction) {
                    return [
                        'id' => $transaction->id,
                        'kode_transaksi' => $transaction->kode_transaksi,
                        'customer_name' => $transaction->customer_name,
                        'queue_number' => $transaction->queue_number,
                        'total_items' => $transaction->total_items,
                        'created_at' => $transaction->created_at,
                        'updated_at' => $transaction->updated_at,
                    ];
                })
        ]);
    }

    public function processOrder(Request $request, Transaction $transaction)
    {
        $request->validate([
            'status' => 'required|in:completed,canceled',
        ]);

        $transaction->status = $request->status;
        $transaction->save();

        return back()->with('success', 'Order has been processed successfully');
    }
}