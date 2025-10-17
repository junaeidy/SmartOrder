<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Midtrans Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration for Midtrans payment gateway.
    |
    */

    'merchant_id' => env('MIDTRANS_MERCHANT_ID', 'G468926321'),
    'client_key' => env('MIDTRANS_CLIENT_KEY', 'Mid-client-z1wVNkZJp-cX3tCq'),
    'server_key' => env('MIDTRANS_SERVER_KEY', 'Mid-server-ypfB5yX3Gp6BG575eIGPKIW5'),
    
    'is_production' => env('MIDTRANS_IS_PRODUCTION', false),
    'is_sanitized' => env('MIDTRANS_IS_SANITIZED', true),
    'is_3ds' => env('MIDTRANS_IS_3DS', true),
];