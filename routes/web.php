<?php

use App\Http\Controllers\Admin\ContractController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('api/public')->group(function (): void {
   
});

Route::middleware(['auth', 'role:admin'])->prefix('api/admin')->group(function (): void {
    Route::get('/dashboard', AdminDashboardController::class);

  
});

Route::view('/{path?}', 'app')
    ->where('path', '^(?!api|up).*$');
