<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /**
     * Display the settings page.
     *
     * @return \Inertia\Response
     */
    public function index()
    {
        // Get all store settings
        $storeHours = [
            'monday_open' => \App\Models\Setting::get('monday_open', '08:00'),
            'monday_close' => \App\Models\Setting::get('monday_close', '20:00'),
            'tuesday_open' => \App\Models\Setting::get('tuesday_open', '08:00'),
            'tuesday_close' => \App\Models\Setting::get('tuesday_close', '20:00'),
            'wednesday_open' => \App\Models\Setting::get('wednesday_open', '08:00'),
            'wednesday_close' => \App\Models\Setting::get('wednesday_close', '20:00'),
            'thursday_open' => \App\Models\Setting::get('thursday_open', '08:00'),
            'thursday_close' => \App\Models\Setting::get('thursday_close', '20:00'),
            'friday_open' => \App\Models\Setting::get('friday_open', '08:00'),
            'friday_close' => \App\Models\Setting::get('friday_close', '20:00'),
            'saturday_open' => \App\Models\Setting::get('saturday_open', '10:00'),
            'saturday_close' => \App\Models\Setting::get('saturday_close', '22:00'),
            'sunday_open' => \App\Models\Setting::get('sunday_open', '10:00'),
            'sunday_close' => \App\Models\Setting::get('sunday_close', '20:00'),
        ];
        
        $storeSettings = [
            'store_name' => \App\Models\Setting::get('store_name', 'SmartOrder'),
            'store_address' => \App\Models\Setting::get('store_address', ''),
            'store_phone' => \App\Models\Setting::get('store_phone', ''),
            'store_email' => \App\Models\Setting::get('store_email', ''),
            'tax_percentage' => \App\Models\Setting::get('tax_percentage', 11),
            'store_closed' => \App\Models\Setting::get('store_closed', false),
        ];
        
        // Get active discounts
        $discounts = \App\Models\Discount::orderBy('created_at', 'desc')->get();
        
        return inertia('Admin/Settings', [
            'storeHours' => $storeHours,
            'storeSettings' => $storeSettings,
            'discounts' => $discounts
        ]);
    }
    
    /**
     * Update the store hours.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateStoreHours(Request $request)
    {
        $request->validate([
            'monday_open' => 'required|string',
            'monday_close' => 'required|string',
            'tuesday_open' => 'required|string',
            'tuesday_close' => 'required|string',
            'wednesday_open' => 'required|string',
            'wednesday_close' => 'required|string',
            'thursday_open' => 'required|string',
            'thursday_close' => 'required|string',
            'friday_open' => 'required|string',
            'friday_close' => 'required|string',
            'saturday_open' => 'required|string',
            'saturday_close' => 'required|string',
            'sunday_open' => 'required|string',
            'sunday_close' => 'required|string',
        ]);
        
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        
        foreach ($days as $day) {
            \App\Models\Setting::set($day . '_open', $request->input($day . '_open'), 'string', 'Store opening hour for ' . ucfirst($day));
            \App\Models\Setting::set($day . '_close', $request->input($day . '_close'), 'string', 'Store closing hour for ' . ucfirst($day));
        }
        
        return redirect()->back()->with('success', 'Store hours updated successfully');
    }
    
    /**
     * Update the store settings.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function updateStoreSettings(Request $request)
    {
        $request->validate([
            'store_name' => 'required|string|max:255',
            'store_address' => 'nullable|string',
            'store_phone' => 'nullable|string|max:20',
            'store_email' => 'nullable|email|max:255',
            'tax_percentage' => 'required|numeric|min:0|max:100',
            'store_closed' => 'boolean',
        ]);
        
        \App\Models\Setting::set('store_name', $request->input('store_name'), 'string', 'Store name');
        \App\Models\Setting::set('store_address', $request->input('store_address'), 'string', 'Store address');
        \App\Models\Setting::set('store_phone', $request->input('store_phone'), 'string', 'Store phone number');
        \App\Models\Setting::set('store_email', $request->input('store_email'), 'string', 'Store email address');
        \App\Models\Setting::set('tax_percentage', $request->input('tax_percentage'), 'number', 'Tax percentage');
        \App\Models\Setting::set('store_closed', $request->input('store_closed', false), 'boolean', 'Store closed status');
        
        return redirect()->back()->with('success', 'Store settings updated successfully');
    }
    
    /**
     * Check if the store is currently open.
     *
     * @return bool
     */
    public static function isStoreOpen()
    {
        // If store is manually closed, return false
        if (\App\Models\Setting::get('store_closed', false)) {
            return false;
        }
        
        // Get current day and time
        $now = now();
        $currentDay = strtolower($now->format('l')); // monday, tuesday, etc.
        $currentTime = $now->format('H:i');
        
        // Get store hours for current day
        $openTime = \App\Models\Setting::get($currentDay . '_open', '08:00');
        $closeTime = \App\Models\Setting::get($currentDay . '_close', '20:00');
        
        // Check if current time is within store hours
        return $currentTime >= $openTime && $currentTime <= $closeTime;
    }
}
