<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Transaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $customer = \App\Models\Customer::factory()->create();
        $totalAmount = fake()->numberBetween(50000, 500000);
        
        return [
            'kode_transaksi' => 'T' . fake()->unique()->numberBetween(10000, 99999),
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
            'total_amount' => $totalAmount,
            'total_items' => fake()->numberBetween(1, 5),
            'payment_method' => fake()->randomElement(['cash', 'online']),
            'queue_number' => fake()->numberBetween(1, 100),
            'status' => 'pending',
            'items' => json_encode([
                [
                    'product_id' => 1,
                    'nama' => 'Sample Product',
                    'harga' => 50000,
                    'quantity' => 1,
                ]
            ]),
        ];
    }

    /**
     * Indicate that the transaction is paid.
     */
    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'paid',
        ]);
    }

    /**
     * Indicate that the transaction is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'confirmed',
        ]);
    }

    /**
     * Indicate that the transaction is completed.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    /**
     * Indicate that the transaction is cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'cancelled',
        ]);
    }

    /**
     * Indicate that the transaction uses cash payment.
     */
    public function cash(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'cash',
        ]);
    }

    /**
     * Indicate that the transaction uses online payment.
     */
    public function online(): static
    {
        return $this->state(fn (array $attributes) => [
            'payment_method' => 'online',
        ]);
    }

    /**
     * Set a specific total amount.
     */
    public function withTotal(int $total): static
    {
        return $this->state(fn (array $attributes) => [
            'total_amount' => $total,
        ]);
    }

    /**
     * Set specific customer data.
     */
    public function forCustomer(\App\Models\Customer $customer): static
    {
        return $this->state(fn (array $attributes) => [
            'customer_name' => $customer->name,
            'customer_email' => $customer->email,
            'customer_phone' => $customer->phone,
        ]);
    }
}
