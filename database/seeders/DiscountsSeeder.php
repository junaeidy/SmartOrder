<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DiscountsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Example discounts
        \App\Models\Discount::create([
            'name' => 'New Customer Special',
            'description' => 'Special discount for new customers',
            'percentage' => 10.00,
            'min_purchase' => 50000,
            'active' => true,
            'valid_from' => now(),
            'valid_until' => now()->addMonths(1),
        ]);
        
        \App\Models\Discount::create([
            'name' => 'Weekend Special',
            'description' => 'Discount for weekend orders',
            'percentage' => 15.00,
            'min_purchase' => 100000,
            'active' => true,
            'valid_from' => now(),
            'valid_until' => now()->addMonths(3),
        ]);
        
        \App\Models\Discount::create([
            'name' => 'Big Order Discount',
            'description' => 'Discount for large orders',
            'percentage' => 20.00,
            'min_purchase' => 200000,
            'active' => true,
        ]);
    }
}
