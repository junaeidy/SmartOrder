<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nama' => fake()->words(3, true),
            'harga' => fake()->numberBetween(10000, 100000),
            'stok' => fake()->numberBetween(0, 100),
            'gambar' => 'https://via.placeholder.com/300',
        ];
    }

    /**
     * Indicate that the product is out of stock.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stok' => 0,
        ]);
    }

    /**
     * Indicate that the product is available.
     */
    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'stok' => fake()->numberBetween(10, 100),
        ]);
    }

    /**
     * Set a specific stock amount.
     */
    public function withStock(int $stock): static
    {
        return $this->state(fn (array $attributes) => [
            'stok' => $stock,
        ]);
    }

    /**
     * Set a specific price.
     */
    public function withPrice(int $price): static
    {
        return $this->state(fn (array $attributes) => [
            'harga' => $price,
        ]);
    }
}
