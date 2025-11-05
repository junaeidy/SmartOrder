# 🍽️ SmartOrder - Smart Restaurant POS System

**SmartOrder** adalah sistem Point of Sale (POS) modern untuk restoran/kafe dengan integrasi mobile ordering, web dashboard admin/kasir, dan dashboard karyawan. Sistem ini menyediakan solusi lengkap dari pemesanan pelanggan hingga manajemen operasional restoran.

## 🌟 Fitur Utama

### 📱 Customer Mobile App (API-based)
- ✅ Registrasi & Login
- ✅ Manajemen profil & foto
- ✅ Browse & search produk
- ✅ Shopping cart & checkout
- ✅ Payment gateway (Cash & Midtrans)
- ✅ Order history & tracking
- ✅ Favorite menu management
- ✅ Discount code verification
- ✅ Push notifications (FCM)
- ✅ Announcements/News
- ✅ Password reset via email

### 💻 Admin/Kasir Web Dashboard
- ✅ Product management (CRUD)
- ✅ Product availability toggle (buka/tutup)
- ✅ Stock alerts monitoring
- ✅ Order processing & confirmation
- ✅ Transaction cancellation
- ✅ Queue number management
- ✅ Transaction reports (PDF/Excel export)
- ✅ Discount management (CRUD)
- ✅ Announcements management (Broadcast push notification)
- ✅ Settings configuration (Store hours, Tax, etc)
- ✅ Real-time order updates (Pusher)

### 👨‍🍳 Karyawan (Staff) Web Dashboard
- ✅ View incoming orders (status: waiting)
- ✅ Process orders (waiting → awaiting_confirmation)
- ✅ Role-based access control
- ✅ Real-time order notifications

## 📊 Status Project

**Production Ready** ✅  
- **Testing:** 64 tests, 124 assertions, 100% pass rate
- **Security Score:** A (95/100)
- **Performance:** <200ms response time, 1-3 queries per request
- **Overall Grade:** A+ (94/100)

📖 Lihat [PROJECT_ANALYSIS.md](PROJECT_ANALYSIS.md) untuk analisis lengkap

## 🛠️ Tech Stack

### Backend
- **Framework:** Laravel 11 (PHP 8.2+)
- **Database:** MySQL
- **Authentication:** Laravel Sanctum (Token-based)
- **Queue System:** Database/Redis
- **Cache:** Redis (optional, fallback to file)

### Frontend (Web Dashboard)
- **Framework:** Inertia.js + React
- **Styling:** Tailwind CSS
- **Build Tool:** Vite

### Third-Party Services
- **Payment Gateway:** Midtrans (Sandbox & Production)
- **Real-time Broadcasting:** Pusher
- **Push Notifications:** Firebase Cloud Messaging (FCM)
- **Email:** SMTP (Mailtrap for development)

## ⚙️ System Requirements

- PHP 8.2 or higher
- Composer 2.x
- Node.js 18+ & NPM
- MySQL 8.0+
- Redis (optional, recommended for production)

## 🚀 Quick Start

### 1. Clone Repository

```bash
git clone https://github.com/junaeidy/SmartOrder.git
cd SmartOrder
```

### 2. Install Dependencies

```bash
composer install
npm install
```

### 3. Environment Setup

Salin `.env.example` ke `.env`:

```bash
cp .env.example .env
```

Konfigurasi database, Midtrans, Pusher, dan Firebase:

```env
# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=smart_order
DB_USERNAME=root
DB_PASSWORD=

# Midtrans Payment Gateway
MIDTRANS_SERVER_KEY=your_midtrans_server_key
MIDTRANS_CLIENT_KEY=your_midtrans_client_key
MIDTRANS_MERCHANT_ID=your_midtrans_merchant_id
MIDTRANS_IS_PRODUCTION=false

# Pusher Real-time Broadcasting
PUSHER_APP_ID=your_pusher_app_id
PUSHER_APP_KEY=your_pusher_app_key
PUSHER_APP_SECRET=your_pusher_app_secret
PUSHER_APP_CLUSTER=ap1

## 📡 API Documentation

API menggunakan **versioning** (`/api/v1`) untuk kompatibilitas. Semua endpoint memerlukan header `Accept: application/json`.

### 🔐 Authentication & Profile

| Method | Endpoint | Auth | Fungsi |
|--------|----------|------|--------|
| POST | `/api/v1/register` | - | Registrasi pelanggan baru |
| POST | `/api/v1/login` | - | Login dan dapatkan token |
| POST | `/api/v1/logout` | ✅ | Logout dan invalidate token |
| GET | `/api/v1/devices` | ✅ | Lihat semua device login |
| POST | `/api/v1/devices/revoke-others` | ✅ | Revoke device lain |
| GET | `/api/v1/profile` | ✅ | Dapatkan info profil |
| PUT | `/api/v1/profile/info` | ✅ | Update profil |
| POST | `/api/v1/profile/info` | ✅ | Update profil + foto (multipart) |
| PUT | `/api/v1/profile/password` | ✅ | Update password |
| POST | `/api/v1/password/send-code` | - | Kirim kode reset password |
| POST | `/api/v1/password/verify-and-reset` | - | Verifikasi & reset password |

### 🍔 Products & Menu

| Method | Endpoint | Auth | Fungsi |
|--------|----------|------|--------|
| GET | `/api/v1/products` | - | Daftar semua produk (tersedia) |
| GET | `/api/v1/products/{id}` | - | Detail produk |
| GET | `/api/v1/products/analytics/top` | - | Produk terpopuler |

### 🛒 Cart & Checkout

| Method | Endpoint | Auth | Fungsi |
|--------|----------|------|--------|
| POST | `/api/v1/cart/validate` | - | Validasi keranjang sebelum checkout |
| GET | `/api/v1/checkout/data` | ✅ | Data untuk checkout (tax, settings) |
| GET | `/api/v1/checkout/idempotency-key` | ✅ | Generate idempotency key |
| POST | `/api/v1/checkout/process` | ✅ | Proses pesanan (Cash/Midtrans) |

### 📦 Orders

| Method | Endpoint | Auth | Fungsi |
|--------|----------|------|--------|
| GET | `/api/v1/orders/history` | ✅ | Riwayat pesanan customer |
| GET | `/api/v1/orders/stats` | ✅ | Statistik pesanan customer |
| GET | `/api/v1/orders/{id}` | ✅ | Detail pesanan |

### 💳 Payment (Midtrans)

| Method | Endpoint | Auth | Fungsi |
|--------|----------|------|--------|
| POST | `/api/v1/payment/notification` | - | Webhook callback dari Midtrans |
| GET | `/api/v1/payment/status/{orderId}` | - | Cek status pembayaran |
| GET | `/api/v1/payment/finish` | - | Konfirmasi transaksi selesai |

### 🎟️ Discount

| Method | Endpoint | Auth | Fungsi |
|--------|----------|------|--------|
| GET | `/api/v1/discounts/available` | - | Daftar diskon tersedia |
| POST | `/api/v1/discount/verify` | - | Verifikasi kode diskon |

### ❤️ Favorite Menu

| Method | Endpoint | Auth | Fungsi |
|--------|----------|------|--------|
| GET | `/api/v1/favorites` | ✅ | Daftar menu favorit |
| POST | `/api/v1/favorites` | ✅ | Tambah ke favorit |
| DELETE | `/api/v1/favorites/{productId}` | ✅ | Hapus dari favorit |
| GET | `/api/v1/favorites/check/{productId}` | ✅ | Cek apakah sudah favorit |

### 📢 Announcements

| Method | Endpoint | Auth | Fungsi |
|--------|----------|------|--------|
| GET | `/api/v1/announcements` | - | Daftar semua pengumuman |
| GET | `/api/v1/announcements/latest` | - | Pengumuman terbaru |
| GET | `/api/v1/announcements/count` | - | Jumlah pengumuman |
| GET | `/api/v1/announcements/{id}` | - | Detail pengumuman |
| GET | `/api/v1/announcements/unread/list` | ✅ | Daftar pengumuman belum dibaca |
| POST | `/api/v1/announcements/{id}/mark-as-read` | ✅ | Tandai sudah dibaca |
| POST | `/api/v1/announcements/{id}/mark-as-unread` | ✅ | Tandai belum dibaca |
| POST | `/api/v1/announcements/mark-all-as-read` | ✅ | Tandai semua sudah dibaca |

### 🔔 Push Notification (FCM)

| Method | Endpoint | Auth | Fungsi |
|--------|----------|------|--------|
| POST | `/api/v1/user/fcm-token` | ✅ | Simpan/update FCM token |
| DELETE | `/api/v1/user/fcm-token/delete` | ✅ | Hapus FCM token |

### ⚙️ Settings

| Method | Endpoint | Auth | Fungsi |
|--------|----------|------|--------|
| GET | `/api/v1/settings` | - | Get store settings (open hours, tax, etc) |

> **📱 Push Notification Setup**: Lihat [PUSHER_SETUP.md](PUSHER_SETUP.md) untuk konfigurasi real-time
> 
> **🔒 Security**: Semua endpoint menggunakan rate limiting untuk mencegah abuse
|--------|----------|--------|
| POST | `/api/v1/register` | Registrasi pelanggan baru |
## 🧪 Testing

Project ini memiliki **64 comprehensive tests** dengan **100% pass rate**:

```bash
# Run all tests
php artisan test

# Run specific test suite
php artisan test --testsuite=Feature
php artisan test --testsuite=Unit

# Run with coverage
php artisan test --coverage
```

**Test Coverage:**
- ✅ Kasir Feature Tests (18 tests)
- ✅ Karyawan Feature Tests (9 tests)
- ✅ Public/Guest Tests (8 tests)
- ✅ Database & API Tests (10 tests)
- ✅ Queue Job Tests (6 tests)
- ✅ Authentication Tests (6 tests)
- ✅ Profile Tests (5 tests)
- ✅ Unit Tests (2 tests)

📖 **Testing Guide:** Lihat [TESTING_DOCUMENTATION.md](TESTING_DOCUMENTATION.md) untuk detail lengkap------|----------|--------|
| GET | `/api/v1/profile` | Dapatkan info profil |
| PUT | `/api/v1/profile` | Update profil |

#### Products & Checkout

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| GET | `/api/v1/products` | Daftar semua produk |
| POST | `/api/v1/cart/validate` | Validasi keranjang |
| GET | `/api/v1/checkout/data` | Data untuk checkout |
| POST | `/api/v1/checkout/process` | Proses pesanan |
| GET | `/api/v1/orders/history` | Riwayat pesanan |
| GET | `/api/v1/orders/{id}` | Detail pesanan |

#### Payment

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| POST | `/api/v1/payment/notification` | Callback dari Midtrans |
| GET | `/api/v1/payment/status/{orderId}` | Cek status pembayaran |
| GET | `/api/v1/payment/finish` | Konfirmasi transaksi selesai |

#### Discount

| Method | Endpoint | Fungsi |
## 📚 Dokumentasi Lengkap

### 🎯 Getting Started
- **[QUICK_START.md](QUICK_START.md)** - Setup development environment
- **[README.md](README.md)** - Overview & API endpoints (this file)
- **[DOCUMENTATION_GUIDE.md](DOCUMENTATION_GUIDE.md)** - Panduan membaca dokumentasi

### 📊 Project Status & Quality
- **[PROJECT_ANALYSIS.md](PROJECT_ANALYSIS.md)** - Analisis komprehensif (Grade: A+)
## 🔄 Business Flow

### 📱 Customer Order Flow
```
1. Customer browse products → Add to cart
2. Validate cart → Apply discount (optional)
3. Checkout (Cash or Midtrans)
4. Order created (status: waiting)
5. Karyawan process order (waiting → awaiting_confirmation)
6. Kasir confirm order (awaiting_confirmation → completed)
   OR Cancel order (if cash payment)
7. Customer receives notification
```

### 💳 Midtrans Payment Flow
```
1. Mobile app calls /checkout/process with paymentMethod: "midtrans"
2. API returns snapToken and redirectUrl
3. Mobile app displays Midtrans payment page
4. After payment, Midtrans calls /payment/notification webhook
5. System updates order status automatically
6. Customer receives push notification
7. Mobile app can check status via /payment/status/{orderId}
```

### 🔔 Notification System
- **Push Notification (FCM)**: Order status updates, announcements
- **Email**: Order confirmation, cancellation
- **Real-time (Pusher)**: Live dashboard updates for kasir/karyawan

## 🏗️ Architecture Highlights

- **Authentication**: Laravel Sanctum (Token-based, Multi-device support)
- **Authorization**: Role-based (kasir, karyawan, customer)
- **Queue System**: Background jobs for email & push notifications
- **Caching**: Products, settings, store status (10min - 1hr TTL)
- **Real-time**: Pusher for live order updates
- **Payment**: Midtrans integration (Sandbox & Production)
- **Security**: XSS protection, CSRF, Rate limiting, Input sanitization
- **Performance**: <200ms response time, 1-3 queries per request

## 🎯 Key Features for Stakeholders

### 📊 Reporting & Analytics
- Transaction reports (PDF/Excel export)
- Product analytics (Top selling products)
- Order statistics per customer
- Stock alerts when running low

### 🔐 Security Features
- Multi-device authentication
- Role-based access control
- Rate limiting on all endpoints
- XSS & SQL injection protection
- Secure payment integration
- Data encryption for sensitive info

### 🚀 Performance Features
- Database indexing for fast queries
- Redis caching (products, settings)
- Eager loading to prevent N+1 queries
- Background job processing
- Health check commands

### 📱 Mobile Features
- Offline-ready (cart validation)
- Push notifications
- Favorite menu management
- Order tracking
- Discount verification
- Multi-device sync

## 🤝 Contributing

1. Fork repository
2. Create feature branch (`git checkout -b feature/AmazingFeature`)
3. Run tests (`php artisan test`)
4. Commit changes (`git commit -m 'Add AmazingFeature'`)
5. Push to branch (`git push origin feature/AmazingFeature`)
6. Open Pull Request

**Note:** All PRs must pass tests and maintain 100% pass rate.

## 📞 Support & Contact

- **Documentation**: [DOCUMENTATION_GUIDE.md](DOCUMENTATION_GUIDE.md)
- **GitHub Issues**: Report bugs or request features
- **Project Owner**: junaeidy

## 📄 License

This project is proprietary software. All rights reserved.

---

**Built with ❤️ using Laravel 11, React, and Inertia.js**

*Last Updated: November 5, 2025*

### 📖 Panduan Membaca
Untuk new team member atau stakeholder, baca dokumentasi sesuai urutan di [DOCUMENTATION_GUIDE.md](DOCUMENTATION_GUIDE.md)
| DELETE | `/api/v1/user/fcm-token/delete` | Hapus FCM token |

> **📱 Push Notification**: Lihat [QUICKSTART_PUSH_NOTIFICATION.md](QUICKSTART_PUSH_NOTIFICATION.md) untuk setup

## Testing

Untuk menjalankan test API:

```bash
php artisan test
```

## Dokumentasi Tambahan

- [OpenAPI Documentation](docs/api/openapi.yaml)
- [Postman Collection](docs/api/postman_collection.json)
- **[🔔 Push Notification Setup](QUICKSTART_PUSH_NOTIFICATION.md)** - Quick start guide
- [Push Notification Implementation](PUSH_NOTIFICATION_IMPLEMENTATION.md) - Complete implementation details
- [Backend Push Notification Setup](BACKEND_PUSH_NOTIFICATION_SETUP.md) - Detailed backend setup
- [Push Notification Guide (Flutter)](PUSH_NOTIFICATION_GUIDE.md) - Flutter/Mobile integration

## Flow Pembayaran dengan Midtrans

1. Aplikasi mobile memanggil `/checkout/process` dengan `paymentMethod: "midtrans"`
2. API mengembalikan `snapToken` dan `redirectUrl`
3. Aplikasi mobile menampilkan halaman pembayaran Midtrans
4. Setelah pembayaran, Midtrans akan memanggil `/payment/notification` 
5. Aplikasi mobile dapat mengecek status dengan `/payment/status/{orderId}`

## Catatan Pengembangan

- API ini menggunakan Laravel Sanctum untuk autentikasi token-based
- Integrasi dengan sistem web yang sudah ada mempertahankan semua logic bisnis yang sama
- Event dan listener yang sama digunakan untuk notifikasi dan email
