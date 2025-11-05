<?php

namespace Database\Factories;

use App\Models\Discount;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Discount>
 */
class DiscountFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Discount::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true) . ' Discount',
            'description' => fake()->sentence(),
            'percentage' => fake()->numberBetween(5, 50),
            'min_purchase' => fake()->numberBetween(10000, 50000),
            'active' => true,
            'valid_from' => now()->subDay(),
            'valid_until' => now()->addDays(30),
        ];
    }

    /**
     * Indicate that the discount is expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_until' => now()->subDay(),
        ]);
    }

    /**
     * Indicate that the discount is not yet valid.
     */
    public function notYetValid(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_from' => now()->addDay(),
        ]);
    }

    /**
     * Indicate that the discount is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    /**
     * Set a specific percentage.
     */
    public function withPercentage(int $percentage): static
    {
        return $this->state(fn (array $attributes) => [
            'percentage' => $percentage,
        ]);
    }

    /**
     * Set minimum purchase amount.
     */
    public function withMinPurchase(int $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'min_purchase' => $amount,
        ]);
    }
}
