<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\CheckoutRequest;
use App\Http\Resources\Api\V1\OrderResource;
use App\Models\Product;
use App\Models\QueueCounter;
use App\Models\Transaction;
use App\Services\MidtransService;
use App\Services\DuplicateOrderProtectionService;
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
    protected $duplicateOrderService;
    
    public function __construct(MidtransService $midtransService, DuplicateOrderProtectionService $duplicateOrderService)
    {
        $this->midtransService = $midtransService;
        $this->duplicateOrderService = $duplicateOrderService;
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
     * Generate idempotency key for checkout protection
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function generateIdempotencyKey(Request $request)
    {
        $customer = $request->user();
        $idempotencyKey = $this->duplicateOrderService->generateIdempotencyKey($customer->email);
        
        return response()->json([
            'success' => true,
            'data' => [
                'idempotency_key' => $idempotencyKey,
                'expires_at' => now()->addMinutes(30)->toISOString() // Key valid for 30 minutes
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

        // 🔒 DUPLICATE ORDER PROTECTION - Step 1: Check Idempotency Key
        $idempotencyKey = $request->header('X-Idempotency-Key');
        if ($idempotencyKey) {
            $existingTransaction = $this->duplicateOrderService->checkIdempotencyKey($idempotencyKey);
            if ($existingTransaction) {
                return response()->json([
                    'success' => true,
                    'message' => 'Pesanan telah berhasil diproses sebelumnya.',
                    'data' => [
                        'transaction' => new OrderResource($existingTransaction),
                        'is_duplicate' => true
                    ]
                ], 200);
            }
        } else {
            // Generate idempotency key if not provided
            $idempotencyKey = $this->duplicateOrderService->generateIdempotencyKey($customer->email);
        }

        // 🔒 DUPLICATE ORDER PROTECTION - Step 2: Generate and Check Order Hash
        $orderData = [
            'customer_email' => $customer->email,
            'items' => $cartItems,
            'payment_method' => $request->paymentMethod,
            'customer_notes' => $request->orderNotes,
            'discount_id' => $request->discountId,
        ];
        
        $orderHash = $this->duplicateOrderService->generateOrderHash($orderData);
        $duplicateCheck = $this->duplicateOrderService->checkDuplicateOrder($customer->email, $orderHash);
        
        if ($duplicateCheck['is_duplicate']) {
            return response()->json([
                'success' => false,
                'message' => $duplicateCheck['message'],
                'error_code' => 'DUPLICATE_ORDER',
                'data' => [
                    'reason' => $duplicateCheck['reason'],
                    'existing_order' => $duplicateCheck['existing_order'] ?? null,
                    'time_remaining' => $duplicateCheck['time_remaining'] ?? null
                ]
            ], 409); // 409 Conflict
        }

        // Record this attempt
        $this->duplicateOrderService->recordAttempt($customer->email, $orderHash);

        $transaction = null;
        $midtransResponse = null;
        $paymentMethod = $request->paymentMethod;

        try {
            $result = DB::transaction(function () use ($customer, $cartItems, $request, $paymentMethod, $orderHash, $idempotencyKey, &$transaction, &$midtransResponse) {
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
                $deviceId = $request->deviceId ?? null; // Get device ID from request
                $discount = $this->getApplicableDiscount($totalAmount, $discountCode, $customer->id, $deviceId);
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
                
                // Set payment expiration time (15 minutes for midtrans, null for cash)
                $paymentExpiresAt = ($paymentMethod === 'midtrans') 
                    ? Carbon::now()->addMinutes(15) 
                    : null;

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
                    'order_hash' => $orderHash,
                    'last_attempt_at' => Carbon::now(),
                    'idempotency_key' => $idempotencyKey,
                    'total_amount' => $totalAmount,
                    'total_items' => $totalItems,
                    'discount_amount' => $discountAmount,
                    'tax_amount' => $taxAmount,
                    'discount_id' => $discount ? $discount->id : null,
                    'payment_method' => $paymentMethod,
                    'payment_status' => $paymentStatus,
                    'payment_expires_at' => $paymentExpiresAt,
                    'queue_number' => $queueNumber,
                    'status' => $initialStatus,
                    'items' => $processedItems,
                ]);
                
                // Record discount usage if discount was applied
                if ($discount && $discountAmount > 0) {
                    $discount->recordUsage(
                        $customer->id,
                        $deviceId,
                        $transaction->id,
                        $discountAmount
                    );
                }

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
            // For midtrans: ONLY send push notification to customer
            // NewOrderReceived event will be triggered AFTER payment confirmation
            event(new \App\Events\OrderStatusChanged($transaction));
            
            $response = response()->json([
                'success' => true,
                'message' => 'Transaksi berhasil dibuat. Silakan lanjutkan pembayaran.',
                'data' => [
                    'transaction' => new OrderResource($transaction),
                    'snapToken' => $midtransResponse['snap_token'],
                    'clientKey' => env('MIDTRANS_CLIENT_KEY', config('midtrans.client_key')),
                    'redirectUrl' => $midtransResponse['redirect_url']
                ]
            ]);
        } else {
            // Cash flow: send email + broadcast after commit
            try {
                Mail::to($transaction->customer_email)->send(new OrderConfirmation($transaction));
            } catch (\Exception $e) {
                Log::error('Error sending email: ' . $e->getMessage());
            }
            
            // Broadcast new order event for kitchen/kasir
            event(new NewOrderReceived($transaction));
            
            // Send push notification to customer
            event(new \App\Events\OrderStatusChanged($transaction));

            $response = response()->json([
                'success' => true,
                'message' => 'Pesanan berhasil dibuat. Silakan menunggu nomor antrian Anda dipanggil.',
                'data' => [
                    'transaction' => new OrderResource($transaction)
                ]
            ]);
        }

        // Add idempotency key to response headers for client reference
        return $response->header('X-Idempotency-Key', $idempotencyKey);
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
     * @param int|null $customerId
     * @param string|null $deviceId
     * @return \App\Models\Discount|null
     */
    private function getApplicableDiscount($amount, $discountCode = null, $customerId = null, $deviceId = null)
    {
        // Get all active discounts that meet minimum purchase requirement
        $query = \App\Models\Discount::where('active', true)
            ->where('min_purchase', '<=', $amount);
            
        if ($discountCode) {
            // If discount code is provided, find that specific discount
            $discount = $query->where('code', $discountCode)->first();
            
            if (!$discount) {
                return null;
            }
            
            // Validate if discount is currently valid (includes date and time validation)
            if (!$discount->isValid()) {
                return null;
            }
            
            // Check if customer/device can use this discount
            if (!$discount->canBeUsedBy($customerId, $deviceId)) {
                throw new \RuntimeException('Anda sudah menggunakan kode diskon ini sebelumnya');
            }
            
            return $discount;
        }
        
        // Get all discounts that don't require code
        $discounts = $query->where('requires_code', false)->get();
        
        if ($discounts->isEmpty()) {
            return null;
        }
        
        // Filter valid discounts (includes date and time validation + usage check)
        $validDiscounts = $discounts->filter(function($discount) use ($customerId, $deviceId) {
            if (!$discount->isValid()) {
                return false;
            }
            
            // Check if customer/device can use this discount
            if (!$discount->canBeUsedBy($customerId, $deviceId)) {
                return false;
            }
            
            return true;
        });
        
        if ($validDiscounts->isEmpty()) {
            return null;
        }
        
        // Return the discount with highest percentage
        return $validDiscounts->sortByDesc('percentage')->first();
    }
}
