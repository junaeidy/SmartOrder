<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use App\Models\QueueCounter;
use App\Services\MidtransService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use App\Mail\OrderConfirmation;
use App\Events\NewOrderReceived;
use Carbon\Carbon;
use App\Events\ProductStockAlert;
use Illuminate\Support\Facades\DB;
use App\Helpers\BroadcastHelper;

class CheckoutController extends Controller
{
    protected $midtransService;
    
    public function __construct(MidtransService $midtransService)
    {
        $this->midtransService = $midtransService;
    }
    
    public function checkout(Request $request)
    {
        return Inertia::render('Checkout', [
            'products' => Product::all()->map(function($product) {
                return [
                    'id' => $product->id,
                    'nama' => $product->nama,
                    'harga' => $product->harga,
                    'stok' => $product->stok,
                    'closed' => (bool)($product->closed ?? false),
                    'gambar' => $product->gambar ? asset('storage/' . $product->gambar) : null,
                ];
            }),
            'taxPercentage' => \App\Models\Setting::get('tax_percentage', 11)
        ]);
    }

    /**
     * Validate cart items availability (real-time pre-check)
     */
    public function validateCart(Request $request)
    {
        $validated = $request->validate([
            'cartItems' => 'required|array',
        ]);

        $cartItems = $validated['cartItems'];
        $issues = [];

        foreach ($cartItems as $productId => $qty) {
            $product = Product::find($productId);
            if (!$product) {
                $issues[] = [
                    'product_id' => $productId,
                    'reason' => 'not_found',
                    'message' => 'Produk tidak ditemukan',
                ];
                continue;
            }
            if ($product->closed) {
                $issues[] = [
                    'product_id' => $product->id,
                    'reason' => 'closed',
                    'message' => 'Produk sedang ditutup sementara',
                    'nama' => $product->nama,
                ];
                continue;
            }
            if ($product->stok <= 0) {
                $issues[] = [
                    'product_id' => $product->id,
                    'reason' => 'out_of_stock',
                    'message' => 'Stok produk habis',
                    'nama' => $product->nama,
                ];
                continue;
            }
            if ($qty > $product->stok) {
                $issues[] = [
                    'product_id' => $product->id,
                    'reason' => 'insufficient',
                    'message' => 'Stok tidak mencukupi untuk jumlah yang diminta',
                    'nama' => $product->nama,
                    'available' => $product->stok,
                ];
            }
        }

        return response()->json([
            'success' => empty($issues),
            'issues' => $issues,
        ]);
    }

    public function process(Request $request)
    {
        // Check if store is open
        if (!$this->isStoreOpen()) {
            return redirect()->route('welcome')
                ->withErrors(['store_closed' => 'Maaf, toko sedang tutup. Silakan coba lagi saat jam operasional.']);
        }
        
        $request->validate([
            'customerData' => 'required|array',
            'customerData.nama' => 'required|string',
            'customerData.whatsapp' => 'required|string',
            'customerData.email' => 'required|email',
            'cartItems' => 'required|array',
            'paymentMethod' => 'required|string|in:cash,midtrans',
        ]);

        $customerData = $request->customerData;
        $cartItems = $request->cartItems;

        $transaction = null;
        $midtransResponse = null;
        $paymentMethod = $request->paymentMethod;

        try {
            $result = DB::transaction(function () use ($customerData, $cartItems, $request, $paymentMethod, &$transaction, &$midtransResponse) {
            $totalAmount = 0;
            $totalItems = 0;
            $processedItems = [];
            $lockedProducts = [];

            // First pass: lock rows and validate availability
            foreach ($cartItems as $productId => $quantity) {
                $product = Product::where('id', $productId)->lockForUpdate()->first();
                if (!$product) {
                    throw new \RuntimeException('Produk tidak ditemukan.');
                }
                if ($quantity <= 0) {
                    continue;
                }
                if ($product->closed || $product->stok <= 0 || $quantity > $product->stok) {
                    throw new \RuntimeException('Stok tidak mencukupi untuk produk: ' . ($product->nama ?? ('#'.$productId)));
                }
                $lockedProducts[] = [$product, $quantity];
            }

            // Compute totals now that validation passed
            foreach ($lockedProducts as [$product, $quantity]) {
                $subtotal = $product->harga * $quantity;
                $totalAmount += $subtotal;
                $totalItems += $quantity;
            }

            // Discounts
            $discountCode = $request->discountCode ?? null;
            $discount = $this->getApplicableDiscount($totalAmount, $discountCode);
            $discountAmount = 0;
            if ($discount) {
                $discountAmount = $discount->calculateDiscount($totalAmount);
                if ($discountAmount) {
                    $totalAmount -= $discountAmount;
                }
            }

            // Tax
            $taxPercentage = \App\Models\Setting::get('tax_percentage', 11);
            $taxAmount = round(($totalAmount * $taxPercentage / 100), 2);
            $totalAmount += $taxAmount;

            // Queue and kode transaksi
            $queueNumber = $this->generateQueueNumber();
            $kodeTransaksi = $this->generateKodeTransaksi();

            // Status awal
            $initialStatus = ($paymentMethod === 'cash') ? 'waiting' : 'waiting_for_payment';
            $paymentStatus = 'pending';

            // Second pass: decrement stocks and build items
            foreach ($lockedProducts as [$product, $quantity]) {
                $prevStock = $product->stok;
                $product->stok -= $quantity;
                $product->save();

                // Broadcast threshold alerts (inside TX is fine; queued to broadcast)
                if ($product->stok <= 0 && $prevStock > 0) {
                    BroadcastHelper::safeBroadcast(new ProductStockAlert($product, 'out_of_stock'));
                } elseif ($product->stok <= 20 && $prevStock > 20) {
                    BroadcastHelper::safeBroadcast(new ProductStockAlert($product, 'low_stock'));
                }

                $processedItems[] = [
                    'id' => $product->id,
                    'nama' => $product->nama,
                    'harga' => $product->harga,
                    'quantity' => $quantity,
                    'subtotal' => $product->harga * $quantity,
                    'gambar' => $product->gambar ? asset('storage/' . $product->gambar) : null,
                ];
            }

            // Create transaction record
            $transaction = Transaction::create([
                'kode_transaksi' => $kodeTransaksi,
                'customer_name' => $customerData['nama'],
                'customer_email' => $customerData['email'],
                'customer_phone' => $customerData['whatsapp'],
                'customer_notes' => $request->orderNotes ?? null,
                'total_amount' => $totalAmount,
                'total_items' => $totalItems,
                'discount_amount' => $discountAmount,
                'tax_amount' => $taxAmount,
                'discount_id' => $discount ? $discount->id : null,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentStatus,
                'queue_number' => $queueNumber,
                'status' => $initialStatus,
                'items' => $processedItems,
            ]);

            // If payment method is midtrans, create payment transaction now
            if ($paymentMethod === 'midtrans') {
                $midtransResponse = $this->midtransService->createTransaction($transaction);
                if (!$midtransResponse['success']) {
                    throw new \RuntimeException('Failed to create payment: ' . $midtransResponse['message']);
                }
            }

                return [
                    'totalAmount' => $totalAmount,
                    'taxAmount' => $taxAmount,
                ];
            });
        } catch (\Throwable $e) {
            return back()->withErrors(['stock' => $e->getMessage()]);
        }

        if (!$transaction) {
            return back()->withErrors(['stock' => 'Terjadi kesalahan saat membuat transaksi.']);
        }

        // After commit: midtrans flow or cash flow
        if ($paymentMethod === 'midtrans') {
            // Debug the response
            \Illuminate\Support\Facades\Log::info('Midtrans Response:', $midtransResponse);
            return Inertia::render('PaymentMidtrans', [
                'transaction' => $transaction,
                'snapToken' => $midtransResponse['snap_token'],
                'clientKey' => env('MIDTRANS_CLIENT_KEY', config('midtrans.client_key')),
                'redirectUrl' => $midtransResponse['redirect_url']
            ]);
        }

        // Cash flow: send email + broadcast after commit
        try {
            Mail::to($customerData['email'])->send(new OrderConfirmation($transaction));
        } catch (\Exception $e) {
            Log::error('Error sending email: ' . $e->getMessage());
        }
        BroadcastHelper::safeBroadcast(new NewOrderReceived($transaction));

        return Inertia::render('ThankYou', [
            'transaction' => $transaction,
        ]);
    }

    public function thankyou(Transaction $transaction)
    {
        // No need to load 'items' as it's a cast attribute, not a relationship
        // The items will be automatically available from the items JSON column

        return Inertia::render('ThankYou', [
            'transaction' => $transaction,
        ]);
    }

    private function generateQueueNumber()
    {
        $now = Carbon::now();
        $today = $now->toDateString();
        
        // Use row-level locking to avoid duplicate numbers under high concurrency
        $counter = QueueCounter::where('date', $today)->lockForUpdate()->first();
        
        if (!$counter) {
            // Create the counter row for today and lock it
            $counter = QueueCounter::create([
                'date' => $today,
                'last_number' => 0
            ]);
            // Re-fetch with lock to ensure exclusive access before increment
            $counter = QueueCounter::where('id', $counter->id)->lockForUpdate()->first();
            
            // Cleanup old rows (best-effort)
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
    
    /**
     * Get the applicable discount for the given amount.
     * Will return the best discount for the customer.
     *
     * @param float $amount
     * @return \App\Models\Discount|null
     */
    private function getApplicableDiscount($amount, $discountCode = null)
    {
        $query = \App\Models\Discount::where('active', true)
            ->where('min_purchase', '<=', $amount)
            ->where(function($query) {
                $now = now();
                $query->whereNull('valid_from')
                    ->orWhere('valid_from', '<=', $now);
            })
            ->where(function($query) {
                $now = now();
                $query->whereNull('valid_until')
                    ->orWhere('valid_until', '>=', $now);
            });
            
        // If discount code is provided, find by code
        if ($discountCode) {
            $discount = $query->where('code', $discountCode)->first();
            if ($discount) {
                return $discount;
            }
            return null;
        }
        
        // Otherwise, only return discounts that don't require a code
        $discounts = $query->where('requires_code', false)->get();
        
        if ($discounts->isEmpty()) {
            return null;
        }
        
        // Return the discount that gives the highest amount off
        return $discounts->sortByDesc('percentage')->first();
    }
    
    /**
     * Check if the store is currently open.
     *
     * @return bool
     */
    private function isStoreOpen()
    {
        // If store is manually closed, return false
        if (\App\Models\Setting::get('store_closed', false)) {
            return false;
        }
        
        // Get current day and time
        $now = now();
        $currentDay = strtolower($now->format('l')); // monday, tuesday, etc.
        $currentTime = $now->format('H:i');
        
        // Get store hours for current day
        $openTime = \App\Models\Setting::get($currentDay . '_open', '08:00');
        $closeTime = \App\Models\Setting::get($currentDay . '_close', '20:00');
        
        // Check if current time is within store hours
        return $currentTime >= $openTime && $currentTime <= $closeTime;
    }
}