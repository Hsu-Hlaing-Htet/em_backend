<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web routes
|--------------------------------------------------------------------------
|
| Laravel serves the API under routes/api.php. This file only exposes a
| lightweight root health response — the Vue app lives on its own origin.
|
*/

Route::get('/', function () {
    return response()->json([
        'message' => 'Rosewood Royale API',
        'status' => 'running',
    ]);
});
