<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // Izinkan request dari Ionic dev server dan Capacitor native app
    'allowed_origins' => [
        'http://localhost:8100',        // Ionic dev server
        'http://localhost',             // Capacitor Android/iOS
        'capacitor://localhost',        // Capacitor iOS
        'http://localhost:4200',        // Angular dev server (fallback)
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 3600,                  // Cache preflight response selama 1 jam

    'supports_credentials' => true,     // Diperlukan untuk Sanctum token auth

];
