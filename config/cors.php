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

    /*
    | Comma-separated exact origins from CORS_ALLOWED_ORIGINS.
    | Falls back to FRONTEND_URL, then local Vite ports for development.
    | Never use "*" when credentials are enabled.
    */
    'allowed_origins' => array_values(array_unique(array_filter(array_map(
        trim(...),
        explode(',', (string) env(
            'CORS_ALLOWED_ORIGINS',
            env('FRONTEND_URL', 'http://localhost:5173,http://localhost:5174'),
        )),
    )))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => ['Content-Disposition'],

    'max_age' => 0,

    // Bearer-token auth (no SPA cookie credentials). Keep false to avoid "*" + credentials.
    'supports_credentials' => (bool) env('CORS_SUPPORTS_CREDENTIALS', false),

];
