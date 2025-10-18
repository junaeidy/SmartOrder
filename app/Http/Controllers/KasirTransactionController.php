<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Inertia\Inertia;

class KasirTransactionController extends Controller
{
    public function index(Request $request)
    {
        $q = Transaction::query()
            ->where('status', 'awaiting_confirmation')
            ->orderBy('queue_number', 'asc');

        $transactions = $q->paginate(20)->through(function ($t) {
            return [
                'id' => $t->id,
                'date' => optional($t->created_at)->toDateTimeString(),
                'created_at' => optional($t->created_at)->toDateTimeString(),
                'updated_at' => optional($t->updated_at)->toDateTimeString(),
                'paid_at' => optional($t->paid_at)->toDateTimeString(),
                'kode_transaksi' => $t->kode_transaksi,
                'customer_name' => $t->customer_name,
                'customer_phone' => $t->customer_phone,
                'customer_email' => $t->customer_email,
                'customer_notes' => $t->customer_notes,
                'queue_number' => $t->queue_number,
                'payment_method' => $t->payment_method === 'midtrans' ? 'online' : $t->payment_method,
                'payment_status' => $t->payment_status,
                'status' => $t->status,
                'total_amount' => $t->total_amount,
                'total_items' => $t->total_items,
                'items' => is_array($t->items) ? $t->items : [],
                'amount_received' => $t->amount_received,
                'change_amount' => $t->change_amount,
                'is_paid' => in_array(strtolower((string)$t->payment_status), ['paid','settlement','capture']),
            ];
        })->withQueryString();

        return Inertia::render('Kasir/Transaksi', [
            'transactions' => $transactions,
        ]);
    }

    public function confirm(Request $request, Transaction $transaction)
    {
        // Only allow if currently awaiting confirmation
        if ($transaction->status !== 'awaiting_confirmation') {
            return back()->with('error', 'Transaksi tidak dalam status menunggu konfirmasi.');
        }

        // If payment method is cash, this is the moment to mark as paid
        if ($transaction->payment_method === 'cash') {
            $request->validate([
                'amount_received' => ['required','numeric','min:'.$transaction->total_amount],
            ], [
                'amount_received.required' => 'Nominal yang diterima wajib diisi.',
                'amount_received.numeric' => 'Nominal yang diterima harus berupa angka.',
                'amount_received.min' => 'Uang yang diterima kurang dari total pembayaran.',
            ]);

            $received = (float) $request->input('amount_received');
            $change = max(0, $received - (float) $transaction->total_amount);

            $transaction->amount_received = $received;
            $transaction->change_amount = $change;
            $transaction->payment_status = 'paid';
            $transaction->paid_at = now();
        }
        $transaction->status = 'completed';
        $transaction->save();

        event(new \App\Events\OrderStatusChanged($transaction));

        return back()->with('success', 'Transaksi dikonfirmasi selesai.');
    }

    public function cancel(Request $request, Transaction $transaction)
    {
        // Only allow cancel on cash, and when awaiting confirmation
        if ($transaction->payment_method !== 'cash') {
            return back()->with('error', 'Pembatalan hanya diperbolehkan untuk pembayaran tunai.');
        }
        if ($transaction->status !== 'awaiting_confirmation') {
            return back()->with('error', 'Transaksi tidak dalam status menunggu konfirmasi.');
        }

        // Restock items
        $items = is_array($transaction->items) ? $transaction->items : [];
        foreach ($items as $it) {
            $productId = $it['id'] ?? null;
            $qty = (int) ($it['quantity'] ?? 0);
            if ($productId && $qty > 0) {
                $product = Product::find($productId);
                if ($product) {
                    $product->stok = (int)$product->stok + $qty;
                    $product->save();
                }
            }
        }

    // Mark transaction canceled and ensure it doesn't count as paid revenue
    $transaction->status = 'canceled';
    $transaction->payment_status = 'canceled';
    $transaction->paid_at = null;
        $transaction->save();

        event(new \App\Events\OrderStatusChanged($transaction));

        return back()->with('success', 'Transaksi dibatalkan dan stok dikembalikan.');
    }
}
