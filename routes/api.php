<?php

use App\Http\Controllers\Admin\BuildingController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ResidentController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\RoomImageController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UtilityTypeController;
use App\Http\Controllers\Auth\AuthController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware(['auth:sanctum', 'role:super_admin,admin'])->group(function (): void {
    Route::apiResource('roles', RoleController::class);
    Route::apiResource('users', UserController::class);
    Route::apiResource('profiles', ProfileController::class);
    Route::apiResource('residents', ResidentController::class)->parameters(['residents' => 'user']);
    Route::apiResource('staff', StaffController::class)->parameters(['staff' => 'user']);

    Route::prefix('properties')->group(function (): void {
        Route::apiResource('buildings', BuildingController::class);
        Route::apiResource('rooms', RoomController::class);
        Route::post('room-images/upload', [RoomImageController::class, 'upload']);
        Route::apiResource('room-images', RoomImageController::class);
    });

    Route::prefix('utilities')->group(function (): void {
        Route::apiResource('types', UtilityTypeController::class);
    });
});
