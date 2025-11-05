<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class KaryawanFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected $karyawan;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create karyawan user
        $this->karyawan = User::factory()->create([
            'name' => 'Karyawan Test',
            'email' => 'karyawan@test.com',
            'role' => 'karyawan',
        ]);
    }

    /** @test */
    public function karyawan_dapat_mengakses_dashboard(): void
    {
        $response = $this->actingAs($this->karyawan)
            ->get(route('karyawan.dashboard'));

        $response->assertStatus(200);
    }

    /** @test */
    public function karyawan_dapat_melihat_daftar_pesanan(): void
    {
        // Create some waiting transactions
        Transaction::factory()->count(5)->create(['status' => 'waiting']);

        $response = $this->actingAs($this->karyawan)
            ->get(route('karyawan.orders'));

        $response->assertStatus(200);
    }

    /** @test */
    public function karyawan_dapat_memproses_pesanan(): void
    {
        $transaction = Transaction::factory()->create(['status' => 'waiting']);

        $response = $this->actingAs($this->karyawan)
            ->put(route('karyawan.orders.process', $transaction), [
                'status' => 'awaiting_confirmation',
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'status' => 'awaiting_confirmation',
        ]);
    }

    /** @test */
    public function karyawan_tidak_dapat_mengakses_halaman_kasir(): void
    {
        $response = $this->actingAs($this->karyawan)
            ->get(route('kasir.dashboard'));

        $response->assertStatus(403);
    }

    /** @test */
    public function karyawan_tidak_dapat_mengelola_produk(): void
    {
        $response = $this->actingAs($this->karyawan)
            ->get(route('products.index'));

        $response->assertStatus(403);
    }

    /** @test */
    public function karyawan_tidak_dapat_mengakses_laporan(): void
    {
        $response = $this->actingAs($this->karyawan)
            ->get(route('kasir.reports'));

        $response->assertStatus(403);
    }

    /** @test */
    public function karyawan_tidak_dapat_mengakses_pengaturan(): void
    {
        $response = $this->actingAs($this->karyawan)
            ->get(route('admin.settings'));

        $response->assertStatus(403);
    }

    /** @test */
    public function karyawan_tidak_dapat_mengelola_diskon(): void
    {
        $discountData = [
            'name' => 'Promo Test',
            'description' => 'Test discount',
            'percentage' => 10,
            'min_purchase' => 10000,
            'active' => true,
            'valid_from' => now(),
            'valid_until' => now()->addDays(7),
        ];

        $response = $this->actingAs($this->karyawan)
            ->post(route('admin.discounts.store'), $discountData);

        $response->assertStatus(403);
    }

    /** @test */
    public function karyawan_tidak_dapat_mengelola_pengumuman(): void
    {
        $response = $this->actingAs($this->karyawan)
            ->get(route('kasir.announcements'));

        $response->assertStatus(403);
    }
}
