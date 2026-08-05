<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chrome executable for HTML → PDF conversion
    |--------------------------------------------------------------------------
    |
    | Used by the shared server-side document PDF converter. Override via
    | DOCUMENTS_CHROME_PATH when Chrome/Chromium lives elsewhere.
    |
    */

    'chrome_path' => env('DOCUMENTS_CHROME_PATH', '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'),

    /*
    |--------------------------------------------------------------------------
    | Document print assets
    |--------------------------------------------------------------------------
    */

    'stylesheet' => resource_path('documents/contract-document.css'),
    'logo' => resource_path('documents/logo-dark.jpg'),

];
