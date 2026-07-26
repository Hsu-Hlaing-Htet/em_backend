<?php

use App\Http\Controllers\Public\PropertyController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/public')->group(function (): void {
    Route::get('properties', [PropertyController::class, 'index']);
    Route::get('properties/featured', [PropertyController::class, 'featured']);
    Route::get('properties/stats', [PropertyController::class, 'stats']);
    Route::get('properties/{property}', [PropertyController::class, 'show']);
});

Route::view('/{path?}', 'app')
    ->where('path', '^(?!api|up).*$');
