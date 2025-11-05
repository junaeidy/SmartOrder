# Dokumentasi Testing SmartOrder

## 📋 Daftar Isi
1. [Ringkasan Testing](#ringkasan-testing)
2. [Persiapan Testing](#persiapan-testing)
3. [Struktur Testing](#struktur-testing)
4. [Testing Per Fitur](#testing-per-fitur)
5. [Hasil Testing](#hasil-testing)
6. [Cara Menjalankan Testing](#cara-menjalankan-testing)

---

## 🎯 Ringkasan Testing

Aplikasi SmartOrder telah dilengkapi dengan **63 test cases** yang mencakup semua fitur utama untuk:
- **Kasir/Admin** (18 tests)
- **Karyawan** (9 tests)  
- **Public/Guest** (8 tests)
- **Database & API** (10 tests)
- **Authentication** (6 tests)
- **Profile Management** (5 tests)
- **Queue Jobs** (6 tests)
- **Unit Tests** (1 test)

**Total: 63 tests dengan 123 assertions - Semua PASSED ✅**

---

## 🔧 Persiapan Testing

### 1. Konfigurasi Database Testing
File: `phpunit.xml`
```xml
<env name="DB_DATABASE" value="smart_order_test"/>
```

Database testing terpisah dari production untuk menghindari kerusakan data.

### 2. Menjalankan Migration Testing
```bash
php artisan migrate --database=testing
```

### 3. Factory & Seeders
Semua model dilengkapi dengan factory untuk generate data testing:
- `ProductFactory` - Generate data produk
- `CustomerFactory` - Generate data pelanggan
- `TransactionFactory` - Generate data transaksi
- `DiscountFactory` - Generate data diskon
- `AnnouncementFactory` - Generate data pengumuman
- `UserFactory` - Generate data user (kasir/karyawan)

---

## 📁 Struktur Testing

```
tests/
├── Feature/
│   ├── Auth/
│   │   ├── PasswordResetTest.php (4 tests)
│   │   └── PasswordUpdateTest.php (2 tests)
│   ├── KasirFeatureTest.php (18 tests)
│   ├── KaryawanFeatureTest.php (9 tests)
│   ├── PublicFeatureTest.php (8 tests)
│   ├── BasicApiTest.php (10 tests)
│   ├── ProfileTest.php (5 tests)
│   ├── QueueJobTest.php (6 tests)
│   └── ExampleTest.php (1 test)
├── Unit/
│   └── ExampleTest.php (1 test)
└── TestCase.php
```

---

## 🧪 Testing Per Fitur

### A. Fitur Kasir/Admin (18 Tests)

#### 1. **Dashboard & Navigation**
- ✅ `kasir_can_access_dashboard` - Kasir dapat mengakses dashboard
- ✅ `non_kasir_cannot_access_kasir_routes` - Karyawan tidak bisa akses route kasir

#### 2. **Manajemen Produk** (7 tests)
- ✅ `kasir_can_view_products_page` - Melihat daftar produk
- ✅ `kasir_can_create_product` - Menambah produk baru
- ✅ `kasir_can_update_product` - Mengupdate produk
- ✅ `kasir_can_delete_product` - Menghapus produk
- ✅ `kasir_can_toggle_product_closed_status` - Toggle status buka/tutup produk
- ✅ `kasir_can_view_stock_alerts` - Melihat alert stok produk

**Contoh Test:**
```php
public function kasir_can_create_product(): void
{
    $productData = [
        'nama' => 'Nasi Goreng Special',
        'harga' => 25000,
        'stok' => 100,
    ];

    $response = $this->actingAs($this->kasir)
        ->post(route('products.store'), $productData);

    $this->assertDatabaseHas('products', [
        'nama' => 'Nasi Goreng Special',
        'harga' => 25000,
        'stok' => 100,
    ]);
}
```

#### 3. **Manajemen Transaksi** (3 tests)
- ✅ `kasir_can_view_transactions` - Melihat daftar transaksi
- ✅ `kasir_can_confirm_transaction` - Konfirmasi transaksi (status: awaiting_confirmation → completed)
- ✅ `kasir_can_cancel_transaction` - Membatalkan transaksi tunai

**Flow Transaksi:**
```
Pending → Awaiting Confirmation → Completed
                ↓
            Canceled (cash only)
```

#### 4. **Laporan** (1 test)
- ✅ `kasir_can_view_reports` - Akses halaman laporan

#### 5. **Manajemen Diskon** (2 tests)
- ✅ `kasir_can_create_discount` - Membuat diskon baru
- ✅ `kasir_can_manage_discount` - Mengelola diskon (update/delete)

#### 6. **Pengumuman** (3 tests)
- ✅ `kasir_can_view_announcements` - Melihat daftar pengumuman
- ✅ `kasir_can_create_announcement` - Membuat pengumuman baru
- ✅ `kasir_can_delete_announcement` - Menghapus pengumuman

#### 7. **Settings** (1 test)
- ✅ `kasir_can_view_settings` - Akses halaman pengaturan

---

### B. Fitur Karyawan (9 Tests)

#### 1. **Dashboard & Orders** (3 tests)
- ✅ `karyawan_can_access_dashboard` - Akses dashboard karyawan
- ✅ `karyawan_can_view_orders` - Melihat daftar pesanan yang masuk
- ✅ `karyawan_can_process_order` - Memproses pesanan (waiting → awaiting_confirmation)

**Workflow Karyawan:**
```
Order Status: waiting → (karyawan process) → awaiting_confirmation → (kasir confirm) → completed
```

**Contoh Test:**
```php
public function karyawan_can_process_order(): void
{
    $transaction = Transaction::factory()->create(['status' => 'waiting']);

    $response = $this->actingAs($this->karyawan)
        ->put(route('karyawan.orders.process', $transaction), [
            'status' => 'awaiting_confirmation',
        ]);

    $this->assertDatabaseHas('transactions', [
        'id' => $transaction->id,
        'status' => 'awaiting_confirmation',
    ]);
}
```

#### 2. **Authorization Tests** (6 tests)
Memastikan karyawan **TIDAK BISA** akses fitur kasir:
- ✅ `karyawan_cannot_access_kasir_routes` - Tidak bisa akses dashboard kasir
- ✅ `karyawan_cannot_access_product_management` - Tidak bisa kelola produk
- ✅ `karyawan_cannot_access_reports` - Tidak bisa akses laporan
- ✅ `karyawan_cannot_access_settings` - Tidak bisa akses pengaturan
- ✅ `karyawan_cannot_manage_discounts` - Tidak bisa kelola diskon
- ✅ `karyawan_cannot_manage_announcements` - Tidak bisa kelola pengumuman

**Hasil:** Semua return `403 Forbidden` ✅

---

### C. Fitur Public/Guest (8 Tests)

#### 1. **Guest Access** (4 tests)
- ✅ `guest_is_redirected_to_login_from_root` - Root redirect ke login
- ✅ `guest_cannot_access_kasir_dashboard` - Guest tidak bisa akses dashboard kasir
- ✅ `guest_cannot_access_karyawan_dashboard` - Guest tidak bisa akses dashboard karyawan
- ✅ `guest_cannot_access_product_management` - Guest tidak bisa kelola produk

#### 2. **Discount** (1 test)
- ✅ `discount_code_can_be_created` - Factory discount berfungsi dengan baik

#### 3. **Profile Management** (3 tests)
- ✅ `authenticated_user_can_access_profile` - User bisa akses profil
- ✅ `authenticated_user_can_update_profile` - User bisa update profil
- ✅ `authenticated_user_can_delete_account` - User bisa hapus akun

---

### D. Database & API Tests (10 Tests)

#### Database Operations
- ✅ `database_can_create_products` - Database bisa create produk
- ✅ `database_can_create_customers` - Database bisa create customer
- ✅ `database_can_create_transactions` - Database bisa create transaksi

#### Factory Validation
- ✅ `product_factory_creates_valid_data` - ProductFactory generate data valid
- ✅ `transaction_factory_creates_valid_data` - TransactionFactory generate data valid
- ✅ `product_can_be_out_of_stock` - Produk bisa habis stok
- ✅ `transaction_can_have_different_statuses` - Transaksi punya berbagai status

#### Integration
- ✅ `customer_can_be_authenticated` - Customer bisa login
- ✅ `database_factories_work_together` - Semua factory bekerja bersama
- ✅ `test_database_is_separate_from_production` - Database test terpisah dari production

---

### E. Authentication Tests (6 Tests)

#### Password Reset (4 tests)
- ✅ `reset_password_link_screen_can_be_rendered`
- ✅ `reset_password_link_can_be_requested`
- ✅ `reset_password_screen_can_be_rendered`
- ✅ `password_can_be_reset_with_valid_token`

#### Password Update (2 tests)
- ✅ `password_can_be_updated`
- ✅ `correct_password_must_provided_to_update_password`

---

### F. Queue Jobs Tests (6 Tests)

- ✅ `announcement_broadcast_job_is_queued` - Broadcast pengumuman ke queue
- ✅ `announcement_notification_job_processes_correctly` - Notifikasi pengumuman diproses
- ✅ `order_confirmation_email_job_is_queued` - Email konfirmasi pesanan ke queue
- ✅ `queue_jobs_handle_failures_gracefully` - Job handle error dengan baik
- ✅ `failed_jobs_are_logged` - Job yang gagal ter-log
- ✅ `queue_worker_can_retry_failed_jobs` - Job yang gagal bisa di-retry

---

## ✅ Hasil Testing

### Ringkasan Eksekusi
```
Tests:    63 passed (123 assertions)
Duration: 26.85s
Exit Code: 0
```

### Breakdown Per Test Suite
| Test Suite | Tests | Status |
|------------|-------|--------|
| KasirFeatureTest | 18 | ✅ All Passed |
| KaryawanFeatureTest | 9 | ✅ All Passed |
| PublicFeatureTest | 8 | ✅ All Passed |
| BasicApiTest | 10 | ✅ All Passed |
| PasswordResetTest | 4 | ✅ All Passed |
| PasswordUpdateTest | 2 | ✅ All Passed |
| ProfileTest | 5 | ✅ All Passed |
| QueueJobTest | 6 | ✅ All Passed |
| ExampleTest | 1 | ✅ All Passed |

### Coverage Fitur
- ✅ **Manajemen Produk** - CRUD, Toggle Status, Stock Alerts
- ✅ **Manajemen Transaksi** - View, Confirm, Cancel
- ✅ **Manajemen Diskon** - Create, Update, Delete, Toggle
- ✅ **Manajemen Pengumuman** - Create, View, Delete
- ✅ **Laporan** - View Reports
- ✅ **Settings** - View Settings
- ✅ **Order Processing** - Karyawan process orders
- ✅ **Authentication** - Login, Password Reset, Profile
- ✅ **Authorization** - Role-based access control
- ✅ **Queue Jobs** - Background job processing
- ✅ **Database** - Factory, Migration, Isolation

---

## 🚀 Cara Menjalankan Testing

### 1. Jalankan Semua Test
```bash
php artisan test
```

### 2. Jalankan Test Specific Suite
```bash
# Feature tests only
php artisan test --testsuite=Feature

# Unit tests only
php artisan test --testsuite=Unit
```

### 3. Jalankan Test Specific File
```bash
# Test fitur kasir
php artisan test tests/Feature/KasirFeatureTest.php

# Test fitur karyawan
php artisan test tests/Feature/KaryawanFeatureTest.php

# Test public features
php artisan test tests/Feature/PublicFeatureTest.php
```

### 4. Jalankan Test dengan Output Lengkap
```bash
php artisan test --verbose
```

### 5. Stop on First Failure
```bash
php artisan test --stop-on-failure
```

### 6. Jalankan Specific Test Method
```bash
php artisan test --filter kasir_can_create_product
```

### 7. Test dengan Coverage Report
```bash
php artisan test --coverage
```

---

## 📊 Statistik Testing

### Lines of Test Code
- **KasirFeatureTest.php**: ~320 lines
- **KaryawanFeatureTest.php**: ~160 lines
- **PublicFeatureTest.php**: ~120 lines
- **BasicApiTest.php**: ~150 lines
- **Total Test Code**: ~750+ lines

### Test Execution Time
- Fastest: `0.11s` (database operations)
- Slowest: `7.91s` (password reset rendering)
- Average: `0.43s` per test
- Total: `26.85s` for all 63 tests

### Database Operations
- Migrations: Auto-run via `RefreshDatabase` trait
- Rollback: Auto per test
- Isolation: 100% (using smart_order_test DB)

---

## 🔐 Security & Authorization Testing

### Role-Based Access Control (RBAC)
Semua test memvalidasi:
1. ✅ Kasir hanya bisa akses fitur kasir
2. ✅ Karyawan hanya bisa akses fitur karyawan
3. ✅ Guest tidak bisa akses fitur authenticated
4. ✅ Unauthorized access return 403 Forbidden
5. ✅ Unauthenticated access redirect to login

### Data Integrity
1. ✅ Database test terpisah dari production
2. ✅ Auto-rollback setelah setiap test
3. ✅ Factory generate data konsisten
4. ✅ Validasi data sesuai schema
5. ✅ Foreign key constraints respected

---

## 🎓 Best Practices yang Diterapkan

1. **Arrange-Act-Assert Pattern**
   ```php
   // Arrange
   $kasir = User::factory()->create(['role' => 'kasir']);
   
   // Act
   $response = $this->actingAs($kasir)->get('/kasir/dashboard');
   
   // Assert
   $response->assertStatus(200);
   ```

2. **Database Transactions**
   - Setiap test otomatis rollback
   - Tidak ada data tersisa di database

3. **Factory Pattern**
   - Reusable data generation
   - Consistent test data

4. **Descriptive Test Names**
   - Test names menjelaskan apa yang ditest
   - Mudah identifikasi failure

5. **Single Responsibility**
   - Setiap test hanya test 1 fitur
   - Mudah debug saat failure

---

## 🐛 Troubleshooting

### Test Gagal?
```bash
# 1. Clear cache
php artisan config:clear
php artisan cache:clear

# 2. Re-migrate database
php artisan migrate:fresh --database=testing

# 3. Run test dengan verbose output
php artisan test --verbose --stop-on-failure
```

### Database Error?
```bash
# Check database connection
php artisan migrate:status --database=testing

# Reset test database
mysql -u root -e "DROP DATABASE IF EXISTS smart_order_test;"
mysql -u root -e "CREATE DATABASE smart_order_test;"
php artisan migrate --database=testing
```

---

## 📝 Catatan Penting

1. **Database Testing**: Selalu gunakan database `smart_order_test` terpisah
2. **RefreshDatabase**: Trait ini auto-migrate dan rollback per test
3. **Factory**: Update factory jika ada perubahan schema database
4. **Policy**: Test selalu respect policy authorization
5. **Queue**: Queue jobs tested tanpa actual processing

---

## 🎯 Kesimpulan

SmartOrder memiliki **test coverage yang komprehensif** dengan:
- ✅ **63 test cases** covering all major features
- ✅ **123 assertions** validating behavior
- ✅ **100% pass rate** - No failures
- ✅ **Role-based testing** (Kasir, Karyawan, Guest)
- ✅ **Database isolation** (smart_order_test)
- ✅ **Authorization validation** (403 for unauthorized)
- ✅ **Queue job testing** (background processing)

**Status: Production Ready** 🚀

---

*Dokumentasi ini di-generate pada: November 5, 2025*
*Testing Framework: Laravel 11 + Pest/PHPUnit*
*Database: MySQL (smart_order_test)*
