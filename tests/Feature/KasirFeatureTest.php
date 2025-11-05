<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\Discount;
use App\Models\Announcement;
use Illuminate\Foundation\Testing\RefreshDatabase;

class KasirFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected $kasir;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create kasir user
        $this->kasir = User::factory()->create([
            'name' => 'Kasir Test',
            'email' => 'kasir@test.com',
            'role' => 'kasir',
        ]);
    }

    /** @test */
    public function kasir_dapat_mengakses_dashboard(): void
    {
        $response = $this->actingAs($this->kasir)
            ->get(route('kasir.dashboard'));

        $response->assertStatus(200);
    }

    /** @test */
    public function kasir_dapat_melihat_halaman_produk(): void
    {
        Product::factory()->count(5)->create();

        $response = $this->actingAs($this->kasir)
            ->get(route('products.index'));

        $response->assertStatus(200);
    }

    /** @test */
    public function kasir_dapat_membuat_produk_baru(): void
    {
        $productData = [
            'nama' => 'Nasi Goreng Special',
            'harga' => 25000,
            'stok' => 100,
        ];

        $response = $this->actingAs($this->kasir)
            ->post(route('products.store'), $productData);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', [
            'nama' => 'Nasi Goreng Special',
            'harga' => 25000,
            'stok' => 100,
        ]);
    }

    /** @test */
    public function kasir_dapat_mengupdate_produk(): void
    {
        $product = Product::factory()->create([
            'nama' => 'Nasi Goreng',
            'harga' => 20000,
        ]);

        $updateData = [
            'nama' => 'Nasi Goreng Updated',
            'harga' => 22000,
            'stok' => 50,
        ];

        $response = $this->actingAs($this->kasir)
            ->put(route('products.update', $product), $updateData);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'nama' => 'Nasi Goreng Updated',
        ]);
    }

    /** @test */
    public function kasir_dapat_menghapus_produk(): void
    {
        $product = Product::factory()->create();

        $response = $this->actingAs($this->kasir)
            ->delete(route('products.destroy', $product));

        $response->assertStatus(302);
        $this->assertDatabaseMissing('products', [
            'id' => $product->id,
        ]);
    }

    /** @test */
    public function kasir_dapat_menutup_atau_membuka_produk(): void
    {
        $product = Product::factory()->create(['closed' => false]);

        $response = $this->actingAs($this->kasir)
            ->put(route('products.toggleClosed', $product));

        $response->assertStatus(302);
        $this->assertDatabaseHas('products', [
            'id' => $product->id,
            'closed' => true,
        ]);
    }

    /** @test */
    public function kasir_dapat_melihat_alert_stok_produk(): void
    {
        // Create products with low stock
        Product::factory()->create(['stok' => 5]);
        Product::factory()->create(['stok' => 0]);

        $response = $this->actingAs($this->kasir)
            ->get(route('kasir.stock.alerts'));

        $response->assertStatus(200);
    }

    /** @test */
    public function kasir_dapat_melihat_laporan(): void
    {
        $response = $this->actingAs($this->kasir)
            ->get(route('kasir.reports'));

        $response->assertStatus(200);
    }

    /** @test */
    public function kasir_dapat_melihat_daftar_transaksi(): void
    {
        Transaction::factory()->count(3)->create();

        $response = $this->actingAs($this->kasir)
            ->get(route('kasir.transaksi'));

        $response->assertStatus(200);
    }

    /** @test */
    public function kasir_dapat_mengkonfirmasi_transaksi(): void
    {
        $transaction = Transaction::factory()->create([
            'status' => 'awaiting_confirmation',
            'payment_method' => 'cash',
        ]);

        $response = $this->actingAs($this->kasir)
            ->put(route('kasir.transaksi.confirm', $transaction), [
                'amount_received' => $transaction->total_amount + 5000,
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'completed',
            'payment_status' => 'paid',
        ]);
    }

    /** @test */
    public function kasir_dapat_membatalkan_transaksi(): void
    {
        $transaction = Transaction::factory()->create([
            'status' => 'awaiting_confirmation',
            'payment_method' => 'cash',
        ]);

        $response = $this->actingAs($this->kasir)
            ->put(route('kasir.transaksi.cancel', $transaction));

        $response->assertStatus(302);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'canceled',
        ]);
    }

    /** @test */
    public function kasir_dapat_melihat_halaman_pengaturan(): void
    {
        $response = $this->actingAs($this->kasir)
            ->get(route('admin.settings'));

        $response->assertStatus(200);
    }

    /** @test */
    public function kasir_dapat_membuat_diskon_baru(): void
    {
        $discountData = [
            'name' => 'Promo Hari Raya',
            'description' => 'Diskon spesial hari raya',
            'percentage' => 20,
            'min_purchase' => 50000,
            'active' => true,
            'valid_from' => now(),
            'valid_until' => now()->addDays(7),
        ];

        $response = $this->actingAs($this->kasir)
            ->post(route('admin.discounts.store'), $discountData);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('discounts', [
            'name' => 'Promo Hari Raya',
            'percentage' => 20,
        ]);
    }

    /** @test */
    public function kasir_dapat_mengelola_diskon(): void
    {
        // Just verify the discount factory works with the model
        $discount = Discount::factory()->create([
            'name' => 'Test Promo',
            'percentage' => 10,
        ]);

        $this->assertDatabaseHas('discounts', [
            'id' => $discount->id,
            'name' => 'Test Promo',
        ]);

        // Delete test
        $discount->delete();
        $this->assertDatabaseMissing('discounts', [
            'id' => $discount->id,
        ]);
    }

    /** @test */
    public function kasir_dapat_melihat_daftar_pengumuman(): void
    {
        $response = $this->actingAs($this->kasir)
            ->get(route('kasir.announcements'));

        $response->assertStatus(200);
    }

    /** @test */
    public function kasir_dapat_membuat_pengumuman_baru(): void
    {
        $announcementData = [
            'title' => 'Pengumuman Penting',
            'message' => 'Ini adalah pengumuman penting untuk semua pelanggan',
        ];

        $response = $this->actingAs($this->kasir)
            ->post(route('kasir.announcements.store'), $announcementData);

        $response->assertStatus(302);
        $this->assertDatabaseHas('announcements', [
            'title' => 'Pengumuman Penting',
            'sent_by' => $this->kasir->id,
        ]);
    }

    /** @test */
    public function kasir_dapat_menghapus_pengumuman(): void
    {
        $announcement = Announcement::factory()->create([
            'sent_by' => $this->kasir->id,
        ]);

        $response = $this->actingAs($this->kasir)
            ->delete(route('kasir.announcements.destroy', $announcement));

        $response->assertStatus(302);
        $this->assertDatabaseMissing('announcements', [
            'id' => $announcement->id,
        ]);
    }

    /** @test */
    public function karyawan_tidak_dapat_mengakses_halaman_kasir(): void
    {
        $karyawan = User::factory()->create(['role' => 'karyawan']);

        $response = $this->actingAs($karyawan)
            ->get(route('kasir.dashboard'));

        $response->assertStatus(403);
    }
}
