<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Store hours
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $defaultHours = [
            'monday' => ['open' => '08:00', 'close' => '20:00'],
            'tuesday' => ['open' => '08:00', 'close' => '20:00'],
            'wednesday' => ['open' => '08:00', 'close' => '20:00'],
            'thursday' => ['open' => '08:00', 'close' => '20:00'],
            'friday' => ['open' => '08:00', 'close' => '20:00'],
            'saturday' => ['open' => '10:00', 'close' => '22:00'],
            'sunday' => ['open' => '10:00', 'close' => '20:00'],
        ];
        
        foreach ($days as $day) {
            \App\Models\Setting::set(
                $day . '_open',
                $defaultHours[$day]['open'],
                'string',
                'Store opening hour for ' . ucfirst($day)
            );
            
            \App\Models\Setting::set(
                $day . '_close',
                $defaultHours[$day]['close'],
                'string',
                'Store closing hour for ' . ucfirst($day)
            );
        }
        
        // Store settings
        \App\Models\Setting::set(
            'store_name',
            'SmartOrder',
            'string',
            'Store name'
        );
        
        \App\Models\Setting::set(
            'store_address',
            'Jalan Jenderal Sudirman No. 123, Jakarta',
            'string',
            'Store address'
        );
        
        \App\Models\Setting::set(
            'store_phone',
            '021-12345678',
            'string',
            'Store phone number'
        );
        
        \App\Models\Setting::set(
            'store_email',
            'info@smartorder.com',
            'string',
            'Store email address'
        );
        
        \App\Models\Setting::set(
            'tax_percentage',
            11,
            'number',
            'Tax percentage'
        );
        
        \App\Models\Setting::set(
            'store_closed',
            false,
            'boolean',
            'Store closed status'
        );
    }
}
