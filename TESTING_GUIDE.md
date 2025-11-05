# 🧪 Panduan Testing SmartOrder

## Ringkasan Project

**SmartOrder** adalah aplikasi pemesanan makanan/minuman berbasis Laravel 11 dengan:
- Mobile App (Flutter/React Native) menggunakan REST API
- Web Dashboard Admin/Kasir menggunakan Inertia.js + React
- Real-time notifications (Pusher + Firebase Cloud Messaging)
- Payment gateway (Midtrans)
- Queue management system
- Multi-device authentication

---

## 📋 Daftar Testing Aplikasi

### A. TESTING FUNGSIONAL

#### 1. **Authentication & Authorization**

| Test Case | Deskripsi | Langkah Testing | Expected Result |
|-----------|-----------|-----------------|-----------------|
| TC-AUTH-001 | Register customer baru | POST `/api/v1/register` dengan data valid | Status 201, customer tersimpan, token diterima |
| TC-AUTH-002 | Register dengan email duplicate | POST dengan email yang sudah ada | Status 422, error validation |
| TC-AUTH-003 | Login dengan kredensial valid | POST `/api/v1/login` dengan email & password benar | Status 200, token Sanctum diterima |
| TC-AUTH-004 | Login dengan kredensial invalid | POST dengan password salah | Status 401, error message |
| TC-AUTH-005 | Logout | POST `/api/v1/logout` dengan token valid | Status 200, token di-revoke |
| TC-AUTH-006 | Multi-device login | Login dari 2 device berbeda | Kedua device mendapat token berbeda |
| TC-AUTH-007 | Revoke other devices | POST `/api/v1/devices/revoke-others` | Device lain logout, current device tetap aktif |
| TC-AUTH-008 | Device token validation | Akses endpoint dengan device token tidak valid | Status 401/403 |

#### 2. **Profile Management**

| Test Case | Deskripsi | Langkah Testing | Expected Result |
|-----------|-----------|-----------------|-----------------|
| TC-PROF-001 | Get profile | GET `/api/v1/profile` | Status 200, data profil lengkap |
| TC-PROF-002 | Update profile info | PUT `/api/v1/profile/info` dengan data baru | Status 200, data ter-update |
| TC-PROF-003 | Upload profile photo | POST `/api/v1/profile/info` dengan multipart/form-data | Status 200, foto tersimpan |
| TC-PROF-004 | Update password | PUT `/api/v1/profile/password` dengan password lama & baru | Status 200, password berubah |
| TC-PROF-005 | Update password salah | PUT dengan old password salah | Status 422, error validation |
| TC-PROF-006 | Password reset flow | POST `/api/v1/password/send-code` → verify code → reset | Email terkirim, kode valid 60 menit |

#### 3. **Products & Catalog**

| Test Case | Deskripsi | Langkah Testing | Expected Result |
|-----------|-----------|-----------------|-----------------|
| TC-PROD-001 | Get all products | GET `/api/v1/products` | Status 200, list produk aktif |
| TC-PROD-002 | Get product detail | GET `/api/v1/products/{id}` | Status 200, detail produk |
| TC-PROD-003 | Filter closed products | GET products saat ada produk closed | Produk closed tidak muncul (atau flag `is_closed`) |
| TC-PROD-004 | Get product not found | GET dengan ID tidak ada | Status 404 |
| TC-PROD-005 | Product analytics | GET `/api/v1/products/analytics/top` | Status 200, list top products |

#### 4. **Cart & Checkout**

| Test Case | Deskripsi | Langkah Testing | Expected Result |
|-----------|-----------|-----------------|-----------------|
| TC-CART-001 | Validate cart - valid | POST `/api/v1/cart/validate` dengan items valid | Status 200, semua item available |
| TC-CART-002 | Validate cart - out of stock | POST dengan item stok habis | Status 200, error message per item |
| TC-CART-003 | Validate cart - closed product | POST dengan produk closed | Error "Produk sedang ditutup sementara" |
| TC-CART-004 | Validate cart - insufficient stock | POST quantity > stock | Error "Stok tidak mencukupi" |
| TC-CART-005 | Get checkout data | GET `/api/v1/checkout/data` | Status 200, tax%, settings, dll |
| TC-CART-006 | Generate idempotency key | GET `/api/v1/checkout/idempotency-key` | Status 200, unique key |

#### 5. **Checkout & Payment**

| Test Case | Deskripsi | Langkah Testing | Expected Result |
|-----------|-----------|-----------------|-----------------|
| TC-CHKT-001 | Checkout cash - success | POST `/api/v1/checkout/process` payment_method=cash | Status 200, queue_number, status=waiting |
| TC-CHKT-002 | Checkout online - success | POST payment_method=midtrans | Status 200, payment_url midtrans |
| TC-CHKT-003 | Checkout saat toko tutup | POST saat di luar jam operasional | Status 400, "Toko sedang tutup" |
| TC-CHKT-004 | Checkout duplicate (idempotency) | POST 2x dengan idempotency_key sama | Request kedua return data transaksi pertama |
| TC-CHKT-005 | Checkout dengan discount valid | POST dengan discount_code valid | Discount teraplikasi, discount_amount > 0 |
| TC-CHKT-006 | Checkout dengan discount invalid | POST dengan kode expired/tidak valid | Discount tidak teraplikasi |
| TC-CHKT-007 | Payment notification (Midtrans) | POST `/api/v1/payment/notification` dengan signature valid | Status 200, order status update |
| TC-CHKT-008 | Payment expired (15 menit) | Tunggu 15 menit setelah checkout online | Order cancelled, stock restored, email sent |
| TC-CHKT-009 | Check payment status | GET `/api/v1/payment/status/{orderId}` | Status 200, payment status terkini |
| TC-CHKT-010 | Checkout rate limiting | POST checkout 4x dalam 5 menit | Request ke-4 status 429 |

#### 6. **Order Management**

| Test Case | Deskripsi | Langkah Testing | Expected Result |
|-----------|-----------|-----------------|-----------------|
| TC-ORD-001 | Get order history | GET `/api/v1/orders/history` | Status 200, list orders customer |
| TC-ORD-002 | Get order detail | GET `/api/v1/orders/{transaction}` | Status 200, detail lengkap order |
| TC-ORD-003 | Get order stats | GET `/api/v1/orders/stats` | Status 200, total orders, total spent |
| TC-ORD-004 | Access other customer order | GET order milik customer lain | Status 403/404 |
| TC-ORD-005 | Order status flow | waiting → awaiting_confirmation → completed | Status berubah, notifikasi terkirim |

#### 7. **Discount & Promotions**

| Test Case | Deskripsi | Langkah Testing | Expected Result |
|-----------|-----------|-----------------|-----------------|
| TC-DISC-001 | Get available discounts | GET `/api/v1/discounts/available` | Status 200, list discount aktif |
| TC-DISC-002 | Verify discount code valid | POST `/api/v1/discount/verify` dengan code valid | Status 200, discount details |
| TC-DISC-003 | Verify discount expired | POST dengan code expired | Status 422, "Discount expired" |
| TC-DISC-004 | Verify discount max usage | POST code yang sudah mencapai max usage | Status 422, "Max usage reached" |
| TC-DISC-005 | Verify discount time constraint | POST code di luar waktu berlaku | Status 422, error message |
| TC-DISC-006 | Multiple discount usage | Gunakan code yang sama 2x oleh customer sama | Hanya bisa digunakan sesuai max_usage_per_customer |

#### 8. **Favorites**

| Test Case | Deskripsi | Langkah Testing | Expected Result |
|-----------|-----------|-----------------|-----------------|
| TC-FAV-001 | Get favorites | GET `/api/v1/favorites` | Status 200, list favorit customer |
| TC-FAV-002 | Add to favorites | POST `/api/v1/favorites` dengan productId | Status 201, produk ditambahkan |
| TC-FAV-003 | Remove from favorites | DELETE `/api/v1/favorites/{productId}` | Status 200, produk dihapus |
| TC-FAV-004 | Check favorite status | GET `/api/v1/favorites/check/{productId}` | Status 200, is_favorite: true/false |
| TC-FAV-005 | Add duplicate favorite | POST produk yang sudah di favorit | Status 422 atau idempotent return existing |

#### 9. **Announcements**

| Test Case | Deskripsi | Langkah Testing | Expected Result |
|-----------|-----------|-----------------|-----------------|
| TC-ANN-001 | Get all announcements | GET `/api/v1/announcements` | Status 200, list announcements |
| TC-ANN-002 | Get latest announcement | GET `/api/v1/announcements/latest` | Status 200, announcement terbaru |
| TC-ANN-003 | Get unread count | GET `/api/v1/announcements/count` | Status 200, unread_count |
| TC-ANN-004 | Get unread list | GET `/api/v1/announcements/unread/list` (auth) | Status 200, list unread |
| TC-ANN-005 | Mark as read | POST `/api/v1/announcements/{id}/mark-as-read` | Status 200, announcement marked |
| TC-ANN-006 | Mark all as read | POST `/api/v1/announcements/mark-all-as-read` | Status 200, semua marked |

#### 10. **FCM Notifications**

| Test Case | Deskripsi | Langkah Testing | Expected Result |
|-----------|-----------|-----------------|-----------------|
| TC-FCM-001 | Store FCM token | POST `/api/v1/user/fcm-token` dengan token | Status 200, token tersimpan |
| TC-FCM-002 | Delete FCM token | DELETE `/api/v1/user/fcm-token/delete` | Status 200, token dihapus |
| TC-FCM-003 | Receive order notification | Ubah status order | Push notification diterima di device |
| TC-FCM-004 | Receive announcement notification | Broadcast announcement baru | Push notification diterima |

---

### B. TESTING NON-FUNGSIONAL

#### 1. **Performance Testing**

| Test Case | Metric | Target | Tools |
|-----------|--------|--------|-------|
| TP-001 | API Response Time | < 200ms untuk GET, < 500ms untuk POST | Apache JMeter, Postman |
| TP-002 | Concurrent Users | Support 100 concurrent users | Apache JMeter |
| TP-003 | Database Query Performance | < 50ms per query | Laravel Debugbar, Telescope |
| TP-004 | Queue Processing Time | < 5s untuk jobs | Monitor queue:work logs |
| TP-005 | File Upload Speed | < 3s untuk foto 5MB | Test upload profile photo |

#### 2. **Security Testing**

| Test Case | Deskripsi | Expected Result |
|-----------|-----------|-----------------|
| TS-001 | SQL Injection | Input payload SQL di form | Laravel ORM mencegah injection |
| TS-002 | XSS Attack | Input script tag di customer_notes | Data ter-sanitize/escape |
| TS-003 | CSRF Protection | POST tanpa CSRF token (web) | Status 419 |
| TS-004 | Rate Limiting | 100 request/menit ke endpoint | Status 429 setelah threshold |
| TS-005 | Authentication bypass | Akses endpoint protected tanpa token | Status 401 |
| TS-006 | Midtrans signature verification | POST payment notification dengan signature palsu | Rejected |
| TS-007 | Device token validation | Gunakan token device yang sudah di-revoke | Status 401 |
| TS-008 | Password hashing | Check database users table | Password di-hash dengan bcrypt |

#### 3. **Email Testing**

| Test Case | Trigger | Expected |
|-----------|---------|----------|
| TE-001 | Order confirmation (cash) | Email konfirmasi dengan queue number |
| TE-002 | Order confirmation (online paid) | Email konfirmasi setelah payment success |
| TE-003 | Order cancellation | Email pembatalan + stock restored message |
| TE-004 | Password reset | Email dengan 6-digit code, expire 60 menit |
| TE-005 | Email content | Bahasa Indonesia, Tunai/Online (bukan cash/midtrans) |

#### 4. **Queue & Background Jobs**

| Test Case | Deskripsi | Expected Result |
|-----------|-----------|-----------------|
| TQ-001 | Queue worker running | `php artisan queue:work` berjalan | Jobs diproses |
| TQ-002 | SendOrderStatusPushNotification | Order status changed | Notifikasi terkirim dalam < 5s |
| TQ-003 | ProcessAnnouncementBroadcast | Announcement created | Broadcast ke all customers |
| TQ-004 | SendAnnouncementNotification | Announcement created | FCM notification terkirim |
| TQ-005 | Failed job handling | Job gagal (network issue) | Masuk failed_jobs table |

#### 5. **Integration Testing**

| Test Case | Integration | Expected Result |
|-----------|-------------|-----------------|
| TI-001 | Midtrans API | Create transaction → Get payment URL | URL valid, accessible |
| TI-002 | Firebase FCM | Send notification | Notification delivered |
| TI-003 | Pusher Broadcasting | Broadcast event | Clients receive event real-time |
| TI-004 | Email Service | Send email | Email delivered (check spam) |
| TI-005 | Database transactions | Checkout → rollback if error | Data consistency terjaga |

---

### C. TESTING EDGE CASES & ERROR HANDLING

| Test Case | Scenario | Expected Behavior |
|-----------|----------|-------------------|
| TEC-001 | Network timeout Midtrans | Graceful error, order tetap tersimpan |
| TEC-002 | Duplicate order_hash | Duplicate detection works, return existing |
| TEC-003 | Stock reduced by other order | Checkout gagal, error "stok habis" |
| TEC-004 | Payment webhook delay | Order update when webhook arrives late |
| TEC-005 | Queue number wrap | Reset counter setiap hari |
| TEC-006 | Concurrent checkout same product | Pessimistic locking works |
| TEC-007 | Discount usage at same time | Max usage enforced correctly |
| TEC-008 | Large cart (50+ items) | Validation & checkout success |
| TEC-009 | Special characters in input | Data sanitized, no error |
| TEC-010 | Timezone handling | Created_at, paid_at correct timezone |

---

## 🛠️ Tools untuk Testing

### 1. **Manual Testing**
- **Postman** - API testing & collection
- **Browser DevTools** - Web dashboard testing
- **Mobile emulator** - Flutter/RN app testing

### 2. **Automated Testing**
- **Pest PHP** (sudah terinstall) - Unit & Feature tests
- **Laravel Dusk** - Browser automation (opsional)
- **PHPUnit** - Fallback testing framework

### 3. **Performance Testing**
- **Apache JMeter** - Load testing
- **Laravel Telescope** - Query monitoring
- **Laravel Debugbar** - Performance profiling

### 4. **Monitoring**
- **Laravel Log Viewer** - Error tracking
- **Sentry** (opsional) - Error monitoring production
- **New Relic** (opsional) - APM

---

## 📝 Contoh Test Script (Pest PHP)

### File: `tests/Feature/API/CheckoutTest.php`

```php
<?php

use App\Models\Customer;
use App\Models\Product;
use Laravel\Sanctum\Sanctum;

test('customer dapat checkout dengan cash payment', function () {
    $customer = Customer::factory()->create();
    Sanctum::actingAs($customer, ['*']);
    
    $product = Product::factory()->create([
        'stock' => 10,
        'price' => 25000
    ]);
    
    $response = $this->postJson('/api/v1/checkout/process', [
        'idempotency_key' => uniqid(),
        'paymentMethod' => 'cash',
        'items' => [
            [
                'product_id' => $product->id,
                'quantity' => 2
            ]
        ],
        'customer_name' => $customer->name,
        'customer_email' => $customer->email,
        'customer_phone' => $customer->phone,
    ]);
    
    $response->assertStatus(200)
        ->assertJsonStructure([
            'success',
            'message',
            'data' => [
                'transaction_id',
                'queue_number',
                'status'
            ]
        ]);
    
    expect($response->json('data.status'))->toBe('waiting');
});

test('checkout gagal saat toko tutup', function () {
    // Mock setting toko tutup
    Setting::updateOrCreate(
        ['key' => 'store_open'],
        ['value' => 'false']
    );
    
    $customer = Customer::factory()->create();
    Sanctum::actingAs($customer, ['*']);
    
    $response = $this->postJson('/api/v1/checkout/process', [
        'idempotency_key' => uniqid(),
        'paymentMethod' => 'cash',
        'items' => []
    ]);
    
    $response->assertStatus(400)
        ->assertJson([
            'success' => false,
            'message' => 'Maaf, toko sedang tutup. Silakan coba lagi saat jam operasional.'
        ]);
});

test('rate limiting works on checkout endpoint', function () {
    $customer = Customer::factory()->create();
    Sanctum::actingAs($customer, ['*']);
    
    // Attempt 4 checkouts in quick succession
    for ($i = 0; $i < 4; $i++) {
        $response = $this->postJson('/api/v1/checkout/process', [
            'idempotency_key' => uniqid(),
            'paymentMethod' => 'cash',
            'items' => []
        ]);
    }
    
    // 4th request should be rate limited
    $response->assertStatus(429);
});
```

---

## ✅ Testing Checklist

### Pre-Production Testing
- [ ] Semua unit tests pass (`php artisan test`)
- [ ] API endpoints tested di Postman
- [ ] Payment flow tested dengan Midtrans sandbox
- [ ] Email templates checked (Bahasa Indonesia)
- [ ] Push notifications tested di real device
- [ ] Rate limiting verified
- [ ] Security headers configured
- [ ] Error handling tested
- [ ] Database migrations tested
- [ ] Queue workers stable

### Production Readiness
- [ ] Environment variables configured
- [ ] SSL certificate installed
- [ ] Database backups automated
- [ ] Monitoring tools setup
- [ ] Log rotation configured
- [ ] Queue supervisor configured
- [ ] Cron jobs scheduled (queue expired orders)
- [ ] Performance tested with expected load
- [ ] Rollback plan documented

---

## 🔄 Regression Testing

Setiap kali ada perubahan code, jalankan:

1. **Unit Tests**: `php artisan test --filter Unit`
2. **Feature Tests**: `php artisan test --filter Feature`
3. **Critical Path Tests**:
   - Register → Login → Checkout → Payment
   - Admin: Create Product → Customer Order → Kasir Process
4. **Smoke Tests**: Test 5-10 endpoint paling kritikal

---

## 📊 Test Coverage Target

| Module | Target Coverage |
|--------|-----------------|
| Models | 80% |
| Controllers | 70% |
| Services | 85% |
| Middleware | 90% |
| Overall | 75% |

Command: `php artisan test --coverage --min=75`

---

Dokumen ini harus di-update setiap ada fitur baru! 🚀
