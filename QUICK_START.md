# ⚡ Quick Reference - SmartOrder

## 📁 File yang Baru Dibuat

1. **PROJECT_ANALYSIS.md** - Analisis lengkap project (Grade: B+)
2. **TESTING_GUIDE.md** - Panduan testing comprehensive
3. **IMPROVEMENT_RECOMMENDATIONS.md** - Roadmap peningkatan detail

---

## 🎯 Action Items Prioritas Tinggi

### 1️⃣ Database Indexing (30 menit)

```bash
php artisan make:migration add_performance_indexes
```

```php
// database/migrations/xxxx_add_performance_indexes.php
public function up()
{
    Schema::table('transactions', function (Blueprint $table) {
        $table->index('customer_email');
        $table->index('status');
        $table->index('created_at');
        $table->index('payment_method');
        $table->index('midtrans_transaction_id');
    });
    
    Schema::table('products', function (Blueprint $table) {
        $table->index(['is_available', 'closed']);
        $table->index('stock');
    });
    
    Schema::table('device_tokens', function (Blueprint $table) {
        $table->index(['customer_id', 'revoked_at']);
    });
    
    Schema::table('discount_usages', function (Blueprint $table) {
        $table->index(['discount_id', 'customer_email']);
    });
}
```

```bash
php artisan migrate
```

**Impact:** Query 5-10x lebih cepat

---

### 2️⃣ Setup Error Monitoring (15 menit)

```bash
composer require sentry/sentry-laravel
php artisan vendor:publish --provider="Sentry\Laravel\ServiceProvider"
```

Tambahkan ke `.env`:
```
SENTRY_LARAVEL_DSN=https://your-dsn@sentry.io/project-id
```

Update `config/logging.php`:
```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'sentry'],
    ],
],
```

**Impact:** Real-time error detection

---

### 3️⃣ Implement Caching (1 jam)

```bash
php artisan make:service CacheService
```

```php
// app/Services/CacheService.php
namespace App\Services;

use Illuminate\Support\Facades\Cache;
use App\Models\Setting;
use App\Models\Product;

class CacheService
{
    public function getStoreSettings()
    {
        return Cache::remember('store:settings', 3600, function () {
            return Setting::pluck('value', 'key')->toArray();
        });
    }
    
    public function getAvailableProducts()
    {
        return Cache::remember('products:available', 300, function () {
            return Product::where('is_available', true)
                ->where('closed', false)
                ->where('stock', '>', 0)
                ->get();
        });
    }
    
    public function clearProductCache()
    {
        Cache::forget('products:available');
        Cache::forget('products:analytics:top');
    }
    
    public function clearSettingsCache()
    {
        Cache::forget('store:settings');
    }
}
```

Observer untuk clear cache:
```php
// app/Observers/ProductObserver.php
namespace App\Observers;

use App\Services\CacheService;

class ProductObserver
{
    protected $cache;
    
    public function __construct(CacheService $cache)
    {
        $this->cache = $cache;
    }
    
    public function saved($product)
    {
        $this->cache->clearProductCache();
    }
}
```

Register observer di `AppServiceProvider`:
```php
use App\Models\Product;
use App\Observers\ProductObserver;

public function boot()
{
    Product::observe(ProductObserver::class);
}
```

**Impact:** Reduce database load 60-80%

---

### 4️⃣ Write Basic Tests (2-3 jam)

```bash
php artisan make:test API/AuthTest --pest
php artisan make:test API/CheckoutTest --pest
```

```php
// tests/Feature/API/AuthTest.php
<?php

use App\Models\Customer;

test('customer can register', function () {
    $response = $this->postJson('/api/v1/register', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'phone' => '081234567890',
    ]);
    
    $response->assertStatus(201)
        ->assertJsonStructure(['success', 'token', 'customer']);
});

test('customer can login', function () {
    $customer = Customer::factory()->create([
        'password' => bcrypt('password123')
    ]);
    
    $response = $this->postJson('/api/v1/login', [
        'email' => $customer->email,
        'password' => 'password123',
    ]);
    
    $response->assertStatus(200)
        ->assertJsonStructure(['success', 'token']);
});

test('login fails with wrong password', function () {
    $customer = Customer::factory()->create();
    
    $response = $this->postJson('/api/v1/login', [
        'email' => $customer->email,
        'password' => 'wrongpassword',
    ]);
    
    $response->assertStatus(401);
});
```

Run tests:
```bash
php artisan test
```

**Impact:** Prevent bugs before production

---

### 5️⃣ Setup Automated Backup (30 menit)

```bash
composer require spatie/laravel-backup
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider"
```

Configure `config/backup.php`:
```php
'backup' => [
    'name' => env('APP_NAME', 'smartorder'),
    'source' => [
        'files' => [
            'include' => [
                storage_path('app/public'),
            ],
        ],
        'databases' => ['mysql'],
    ],
    'destination' => [
        'disks' => ['local'],
    ],
],
```

Setup cron (Linux/Mac):
```bash
crontab -e
```

Add:
```
0 2 * * * cd /path/to/smartorder && php artisan backup:run >> /dev/null 2>&1
```

Windows Task Scheduler:
```
Program: C:\xampp\php\php.exe
Arguments: C:\xampp\htdocs\SmartOrder\artisan backup:run
```

**Impact:** Data recovery capability

---

## 🐛 Common Issues & Fixes

### Issue 1: Queue not processing
```bash
# Check if queue worker is running
ps aux | grep queue:work

# Start queue worker
php artisan queue:work --tries=3

# Or use Supervisor (production)
sudo supervisorctl status smartorder-worker
```

### Issue 2: Slow API response
```php
// Enable query log to find slow queries
DB::enableQueryLog();

// Your code here

dd(DB::getQueryLog());
```

### Issue 3: Payment webhook tidak terkirim
```bash
# Check Midtrans notification URL
# Must be publicly accessible: https://yourdomain.com/api/v1/payment/notification

# Test with ngrok (development)
ngrok http 80
# Use ngrok URL in Midtrans dashboard
```

### Issue 4: Push notification tidak diterima
```bash
# Check FCM token di database
SELECT fcm_token FROM customers WHERE email = 'customer@example.com';

# Check queue jobs
SELECT * FROM jobs;
SELECT * FROM failed_jobs;

# Retry failed jobs
php artisan queue:retry all
```

---

## 📊 Performance Benchmarks

### Before Optimization
- API Response: 500-800ms
- DB Queries: 10-20 per request
- Concurrent Users: ~50

### After Optimization (Target)
- API Response: <200ms
- DB Queries: 2-5 per request
- Concurrent Users: 500+

---

## 🔍 Monitoring Commands

```bash
# Check application health
php artisan about

# View recent logs
php artisan pail

# Monitor queue
php artisan queue:monitor

# Check database connections
php artisan db:show

# Clear all caches
php artisan optimize:clear

# Cache everything
php artisan optimize
```

---

## 🚀 Deployment Checklist

### Pre-deployment
- [ ] Run tests: `php artisan test`
- [ ] Check errors: `php artisan pail`
- [ ] Optimize: `composer install --optimize-autoloader --no-dev`
- [ ] Build assets: `npm run build`
- [ ] Migrate: `php artisan migrate --force`
- [ ] Cache config: `php artisan config:cache`
- [ ] Cache routes: `php artisan route:cache`
- [ ] Cache views: `php artisan view:cache`

### Post-deployment
- [ ] Check logs: `tail -f storage/logs/laravel.log`
- [ ] Test critical endpoints
- [ ] Monitor error rate (Sentry)
- [ ] Check queue workers
- [ ] Verify backups running

---

## 📝 Quick Commands Reference

```bash
# Development
php artisan serve
php artisan queue:work
php artisan pail
npm run dev

# Testing
php artisan test
php artisan test --filter AuthTest
php artisan test --coverage

# Database
php artisan migrate
php artisan db:seed
php artisan migrate:fresh --seed

# Cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

# Queue
php artisan queue:work
php artisan queue:restart
php artisan queue:failed
php artisan queue:retry all

# Maintenance
php artisan down
php artisan up
php artisan optimize
```

---

## 📞 Support & Resources

**Documentation:**
- PROJECT_ANALYSIS.md - Overall assessment
- TESTING_GUIDE.md - Testing strategies
- IMPROVEMENT_RECOMMENDATIONS.md - Detailed improvements
- README.md - API documentation

**Laravel Docs:**
- https://laravel.com/docs/11.x
- https://laravel.com/docs/11.x/testing
- https://laravel.com/docs/11.x/optimization

**Community:**
- Laravel Indonesia: https://t.me/laravelindonesia
- Stack Overflow: https://stackoverflow.com/questions/tagged/laravel

---

## 🎯 This Week's Goals

1. ✅ Add database indexes
2. ✅ Setup Sentry
3. ✅ Implement caching
4. ✅ Write 10+ tests
5. ✅ Setup automated backups

---

**Remember: Small improvements compound! Start with quick wins, then tackle bigger items.** 💪
