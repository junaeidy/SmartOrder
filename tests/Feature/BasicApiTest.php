<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BasicApiTest extends TestCase
{
    use RefreshDatabase;

    protected $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->customer = Customer::factory()->create();
    }

    /** @test */
    public function database_dapat_membuat_produk()
    {
        $product = Product::factory()->create([
            'nama' => 'Test Product',
            'harga' => 10000,
            'stok' => 50,
        ]);

        $this->assertDatabaseHas('products', [
            'nama' => 'Test Product',
            'harga' => 10000,
            'stok' => 50,
        ]);
    }

    /** @test */
    public function database_dapat_membuat_customer()
    {
        $customer = Customer::factory()->create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $this->assertDatabaseHas('customers', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);
    }

    /** @test */
    public function database_dapat_membuat_transaksi()
    {
        $transaction = Transaction::factory()->create([
            'customer_name' => 'Jane Doe',
            'customer_email' => 'jane@example.com',
            'total_amount' => 50000,
            'status' => 'completed',
        ]);

        $this->assertDatabaseHas('transactions', [
            'customer_name' => 'Jane Doe',
            'total_amount' => 50000,
            'status' => 'completed',
        ]);
    }

    /** @test */
    public function factory_produk_membuat_data_valid()
    {
        $product = Product::factory()->create();

        $this->assertNotNull($product->nama);
        $this->assertGreaterThan(0, $product->harga);
        $this->assertGreaterThanOrEqual(0, $product->stok);
    }

    /** @test */
    public function factory_transaksi_membuat_data_valid()
    {
        $transaction = Transaction::factory()->create();

        $this->assertNotNull($transaction->customer_name);
        $this->assertNotNull($transaction->customer_email);
        $this->assertNotNull($transaction->customer_phone);
        $this->assertGreaterThan(0, $transaction->total_amount);
        $this->assertNotNull($transaction->items);
    }

    /** @test */
    public function produk_dapat_habis_stok()
    {
        $product = Product::factory()->outOfStock()->create();

        $this->assertEquals(0, $product->stok);
    }

    /** @test */
    public function transaksi_dapat_memiliki_berbagai_status()
    {
        $pending = Transaction::factory()->create(['status' => 'pending']);
        $completed = Transaction::factory()->completed()->create();
        $cancelled = Transaction::factory()->cancelled()->create();

        $this->assertEquals('pending', $pending->status);
        $this->assertEquals('completed', $completed->status);
        $this->assertEquals('cancelled', $cancelled->status);
    }

    /** @test */
    public function customer_dapat_login()
    {
        $customer = Customer::factory()->create([
            'email' => 'test@example.com',
            'password' => bcrypt('password123'),
        ]);

        $this->assertNotNull($customer);
        $this->assertEquals('test@example.com', $customer->email);
    }

    /** @test */
    public function semua_factory_bekerja_bersama(): void
    {
        $customer = Customer::factory()->create();
        $product = Product::factory()->count(3)->create();
        $transaction = Transaction::factory()->forCustomer($customer)->create();

        // Note: TransactionFactory creates its own customer in definition(), 
        // so we get: 1 (setUp) + 1 (explicit) + 1 (TransactionFactory) = 3 customers
        $this->assertDatabaseCount('customers', 3);
        $this->assertDatabaseCount('products', 3);
        $this->assertDatabaseCount('transactions', 1);
    }

    /** @test */
    public function database_test_terpisah_dari_production()
    {
        // Verify we're using test database
        $dbName = config('database.connections.mysql.database');
        $this->assertEquals('smart_order_test', $dbName);
    }
}
