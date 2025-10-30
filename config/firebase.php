<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Firebase Configuration
    |--------------------------------------------------------------------------
    |
    | Configure Firebase services for the application.
    | You need to download the service account JSON file from Firebase Console.
    |
    | For development: storage/app/firebase/service-account.json
    | For production: Use absolute path or set via environment variable
    |
    */

    'credentials' => [
        'file' => env('FIREBASE_CREDENTIALS', storage_path('app/firebase/service-account.json')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Firebase Project Configuration
    |--------------------------------------------------------------------------
    |
    | Your Firebase project configuration details.
    |
    */

    'project_id' => env('FIREBASE_PROJECT_ID', ''),
    
    'database_url' => env('FIREBASE_DATABASE_URL', ''),

    /*
    |--------------------------------------------------------------------------
    | Firebase Cloud Messaging (FCM)
    |--------------------------------------------------------------------------
    |
    | Configuration for Firebase Cloud Messaging.
    |
    */

    'fcm' => [
        'enabled' => env('FCM_ENABLED', true),
    ],

];
