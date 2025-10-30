# SmartOrder Mobile API

Dokumentasi untuk mengintegrasikan dan menggunakan SmartOrder Mobile API. API ini digunakan untuk aplikasi mobile pelanggan yang memungkinkan pelanggan untuk melakukan registrasi, login, melihat produk, melakukan checkout, dan memantau status pesanan mereka.

## Kebutuhan Server

- PHP 8.1 atau lebih tinggi
- Laravel 10.x
- Database MySQL/PostgreSQL
- Composer
- Midtrans account untuk integrasi pembayaran

## Instalasi & Setup

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

### 3. Setup Environment

Salin `.env.example` ke `.env` dan konfigurasi database dan Midtrans:

```bash
cp .env.example .env
```

Tambahkan konfigurasi Midtrans:

```
MIDTRANS_SERVER_KEY=your_midtrans_server_key
MIDTRANS_CLIENT_KEY=your_midtrans_client_key
MIDTRANS_MERCHANT_ID=your_midtrans_merchant_id
MIDTRANS_IS_PRODUCTION=false
```

### 4. Generate Key & Run Migrations

```bash
php artisan key:generate
php artisan migrate
php artisan db:seed
```

### 5. Jalankan Sanctum Setup

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

## Struktur API

API ini menggunakan versioning untuk memastikan kompatibilitas di masa depan. Semua endpoint dalam dokumentasi ini berada di bawah prefix `/api/v1`.

### Endpoints

#### Authentication

| Method | Endpoint | Fungsi |
|--------|----------|--------|
| POST | `/api/v1/register` | Registrasi pelanggan baru |
| POST | `/api/v1/login` | Login dan dapatkan token |
| POST | `/api/v1/logout` | Logout dan invalidate token |

#### Profile

| Method | Endpoint | Fungsi |
|--------|----------|--------|
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
|--------|----------|--------|
| POST | `/api/v1/discount/verify` | Verifikasi kode diskon |

## Testing

Untuk menjalankan test API:

```bash
php artisan test
```

## Dokumentasi Tambahan

- [OpenAPI Documentation](docs/api/openapi.yaml)
- [Postman Collection](docs/api/postman_collection.json)

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
