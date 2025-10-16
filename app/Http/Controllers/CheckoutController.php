<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\QueueCounter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\OrderConfirmation;
use App\Events\NewOrderReceived;
use Carbon\Carbon;

class CheckoutController extends Controller
{
    public function checkout(Request $request)
    {
        return Inertia::render('Checkout', [
            'products' => Product::all()->map(function($product) {
                return [
                    'id' => $product->id,
                    'nama' => $product->nama,
                    'harga' => $product->harga,
                    'stok' => $product->stok,
                    'gambar' => $product->gambar ? asset('storage/' . $product->gambar) : null,
                ];
            })
        ]);
    }

    public function process(Request $request)
    {
        $request->validate([
            'customerData' => 'required|array',
            'customerData.nama' => 'required|string',
            'customerData.whatsapp' => 'required|string',
            'customerData.email' => 'required|email',
            'cartItems' => 'required|array',
        ]);

        $customerData = $request->customerData;
        $cartItems = $request->cartItems;
        
        $totalAmount = 0;
        $totalItems = 0;
        $processedItems = [];

        foreach ($cartItems as $productId => $quantity) {
            $product = Product::find($productId);
            
            if ($product && $quantity > 0) {
                $subtotal = $product->harga * $quantity;
                $totalAmount += $subtotal;
                $totalItems += $quantity;

                $product->stok -= $quantity;
                $product->save();

                $processedItems[] = [
                    'id' => $product->id,
                    'nama' => $product->nama,
                    'harga' => $product->harga,
                    'quantity' => $quantity,
                    'subtotal' => $subtotal,
                    'gambar' => $product->gambar ? asset('storage/' . $product->gambar) : null,
                ];
            }
        }


        $queueNumber = $this->generateQueueNumber();
        $kodeTransaksi = $this->generateKodeTransaksi();

        $transaction = Transaction::create([
            'kode_transaksi' => $kodeTransaksi,
            'customer_name' => $customerData['nama'],
            'customer_email' => $customerData['email'],
            'customer_phone' => $customerData['whatsapp'],
            'customer_notes' => $request->orderNotes ?? null,
            'total_amount' => $totalAmount,
            'total_items' => $totalItems,
            'payment_method' => 'cash',
            'queue_number' => $queueNumber,
            'status' => 'waiting',
            'items' => $processedItems,
        ]);

        // Send email confirmation
        try {
            Mail::to($customerData['email'])->send(new OrderConfirmation($transaction));
        } catch (\Exception $e) {
            Log::error('Error sending email: ' . $e->getMessage());
        }
        
        // Broadcast the new order event for real-time updates
        event(new NewOrderReceived($transaction));

        return Inertia::render('ThankYou', [
            'transaction' => $transaction,
        ]);
    }

    private function generateQueueNumber()
    {
        $now = Carbon::now();
        $today = $now->toDateString();
        
        $counter = QueueCounter::where('date', $today)->first();
        
        if (!$counter) {
            $counter = QueueCounter::create([
                'date' => $today,
                'last_number' => 0
            ]);
            
            QueueCounter::where('date', '<', $today)->delete();
            
            $this->info('New queue counter created for ' . $today);
        }
        
        $counter->last_number += 1;
        $counter->save();
        
        return sprintf('%03d', $counter->last_number);
    }
    
    /**
     * Log info message in development environments
     */
    private function info($message)
    {
        if (config('app.debug')) {
            Log::info($message);
        }
    }
    /**
     * Generate unique kode transaksi in SO-XXXXX format (8 chars, mix of letters and numbers)
     */
    private function generateKodeTransaksi()
    {
        $prefix = 'SO-';
        do {
            $random = strtoupper(substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 5));
            $kode = $prefix . $random;
        } while (Transaction::where('kode_transaksi', $kode)->exists());
        return $kode;
    }
}