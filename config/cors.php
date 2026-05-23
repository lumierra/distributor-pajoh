<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Konfigurasi CORS untuk dual-client architecture:
    | - Web admin (Inertia + session cookie) di domain APP_URL
    | - Mobile sales (Capacitor Bearer token) dengan scheme capacitor://localhost
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie', 'login', 'logout'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_filter(explode(',', env('CORS_ALLOWED_ORIGINS', implode(',', [
        env('APP_URL', 'https://distributor.pajoh.test'),
        'https://distributor.pajoh.test',
        'capacitor://localhost',
        'ionic://localhost',
        'http://localhost',
    ])))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
