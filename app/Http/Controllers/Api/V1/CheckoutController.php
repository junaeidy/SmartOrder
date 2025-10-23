<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CheckoutRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Product;
use App\Models\QueueCounter;
use App\Models\Transaction;
use App\Services\MidtransService;
use Carbon\Carbon;
use App\Events\NewOrderReceived;
use App\Events\ProductStockAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\OrderConfirmation;

class CheckoutController extends Controller
{
    protected $midtransService;
    
    public function __construct(MidtransService $midtransService)
    {
        $this->midtransService = $midtransService;
    }

    /**
     * Validate cart items availability (real-time pre-check)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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

    /**
     * Get checkout data for the user
     * 
     * @return \Illuminate\Http\JsonResponse
     */
    public function checkoutData()
    {
        $taxPercentage = \App\Models\Setting::get('tax_percentage', 11);
        $isStoreOpen = $this->isStoreOpen();
        
        return response()->json([
            'success' => true,
            'data' => [
                'taxPercentage' => $taxPercentage,
                'isStoreOpen' => $isStoreOpen,
            ]
        ]);
    }
    
    /**
     * Process checkout and create transaction
     * 
     * @param CheckoutRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function process(CheckoutRequest $request)
    {
        // Check if store is open
        if (!$this->isStoreOpen()) {
            return response()->json([
                'success' => false,
                'message' => 'Maaf, toko sedang tutup. Silakan coba lagi saat jam operasional.'
            ], 400);
        }
        
        $customer = $request->user();
        $cartItems = $request->cartItems;

        $transaction = null;
        $midtransResponse = null;
        $paymentMethod = $request->paymentMethod;

        try {
            $result = DB::transaction(function () use ($customer, $cartItems, $request, $paymentMethod, &$transaction, &$midtransResponse) {
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
                        event(new ProductStockAlert($product, 'out_of_stock'));
                    } elseif ($product->stok <= 20 && $prevStock > 20) {
                        event(new ProductStockAlert($product, 'low_stock'));
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
                    'customer_name' => $customer->name,
                    'customer_email' => $customer->email,
                    'customer_phone' => $customer->phone,
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
            Log::error('Checkout error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }

        if (!$transaction) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan saat membuat transaksi.'
            ], 500);
        }

        // After commit: midtrans flow or cash flow
        if ($paymentMethod === 'midtrans') {
            return response()->json([
                'success' => true,
                'data' => [
                    'transaction' => new OrderResource($transaction),
                    'snapToken' => $midtransResponse['snap_token'],
                    'clientKey' => env('MIDTRANS_CLIENT_KEY', config('midtrans.client_key')),
                    'redirectUrl' => $midtransResponse['redirect_url']
                ]
            ]);
        }

        // Cash flow: send email + broadcast after commit
        try {
            Mail::to($transaction->customer_email)->send(new OrderConfirmation($transaction));
        } catch (\Exception $e) {
            Log::error('Error sending email: ' . $e->getMessage());
        }
        event(new NewOrderReceived($transaction));

        return response()->json([
            'success' => true,
            'message' => 'Transaksi berhasil dibuat',
            'data' => [
                'transaction' => new OrderResource($transaction)
            ]
        ]);
    }

    /**
     * Get order history for the authenticated customer
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function history(Request $request)
    {
        $customer = $request->user();
        $transactions = Transaction::where('customer_email', $customer->email)
            ->orderBy('created_at', 'desc')
            ->paginate(10);
        
        return response()->json([
            'success' => true,
            'data' => [
                'transactions' => OrderResource::collection($transactions),
                'pagination' => [
                    'current_page' => $transactions->currentPage(),
                    'last_page' => $transactions->lastPage(),
                    'per_page' => $transactions->perPage(),
                    'total' => $transactions->total()
                ]
            ]
        ]);
    }
    
    /**
     * Get detailed order information
     * 
     * @param Transaction $transaction
     * @return \Illuminate\Http\JsonResponse
     */
    public function show(Request $request, $id)
    {
        $customer = $request->user();
        $transaction = Transaction::findOrFail($id);
        
        // Check if the transaction belongs to the authenticated customer
        if ($transaction->customer_email !== $customer->email) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki akses ke transaksi ini'
            ], 403);
        }
        
        return response()->json([
            'success' => true,
            'data' => new OrderResource($transaction)
        ]);
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

    private function info($message)
    {
        if (config('app.debug')) {
            Log::info($message);
        }
    }
    
    /**
     * Generate a unique queue number.
     *
     * @return string
     */
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
     * Generate a unique transaction code.
     *
     * @return string
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
     * Get applicable discount for the given amount and code.
     *
     * @param float $totalAmount
     * @param string|null $discountCode
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
            
        if ($discountCode) {
            $discount = $query->where('code', $discountCode)->first();
            if ($discount) {
                return $discount;
            }
            return null;
        }
        
        $discounts = $query->where('requires_code', false)->get();
        
        if ($discounts->isEmpty()) {
            return null;
        }
        
        return $discounts->sortByDesc('percentage')->first();
    }
}
