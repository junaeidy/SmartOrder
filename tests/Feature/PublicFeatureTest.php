<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\Discount;
use Illuminate\Foundation\Testing\RefreshDatabase;

class PublicFeatureTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function pengunjung_diarahkan_ke_halaman_login(): void
    {
        $response = $this->get('/');

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function kode_diskon_dapat_dibuat(): void
    {
        // Just test that discount factory works
        $discount = Discount::factory()->create([
            'name' => 'PROMO10',
            'percentage' => 10,
            'min_purchase' => 50000,
            'active' => true,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDay(),
        ]);

        $this->assertDatabaseHas('discounts', [
            'name' => 'PROMO10',
            'percentage' => 10,
        ]);
    }

    /** @test */
    public function pengunjung_tidak_dapat_mengakses_dashboard_kasir(): void
    {
        $response = $this->get(route('kasir.dashboard'));

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function pengunjung_tidak_dapat_mengakses_dashboard_karyawan(): void
    {
        $response = $this->get(route('karyawan.dashboard'));

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function pengunjung_tidak_dapat_mengelola_produk(): void
    {
        $response = $this->get(route('products.index'));

        $response->assertStatus(302);
        $response->assertRedirect(route('login'));
    }

    /** @test */
    public function pengguna_dapat_mengakses_halaman_profil(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->get(route('profile.edit'));

        $response->assertStatus(200);
    }

    /** @test */
    public function pengguna_dapat_memperbarui_profil(): void
    {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@test.com',
        ]);

        $response = $this->actingAs($user)
            ->patch(route('profile.update'), [
                'name' => 'Updated Name',
                'email' => 'original@test.com',
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
        ]);
    }

    /** @test */
    public function pengguna_dapat_menghapus_akun(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->delete(route('profile.destroy'), [
                'password' => 'password',
            ]);

        $response->assertStatus(302);
        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }
}
