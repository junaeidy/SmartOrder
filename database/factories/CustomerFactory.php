<?php

namespace Database\Factories;

use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customer>
 */
class CustomerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Customer::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '08' . fake()->numerify('##########'),
            'password' => Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the customer has a profile photo.
     */
    public function withProfilePhoto(): static
    {
        return $this->state(fn (array $attributes) => [
            'profile_photo' => 'profile_photos/' . fake()->uuid() . '.jpg',
        ]);
    }

    /**
     * Indicate that the customer has FCM token.
     */
    public function withFcmToken(): static
    {
        return $this->state(fn (array $attributes) => [
            'fcm_token' => 'fcm_token_' . Str::random(40),
        ]);
    }
}
