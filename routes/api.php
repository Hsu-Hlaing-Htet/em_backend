<?php

use App\Http\Controllers\Admin\BuildingController;
use App\Http\Controllers\Admin\ChargeTypeController;
use App\Http\Controllers\Admin\ContractController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\InvoiceItemController;
use App\Http\Controllers\Admin\LateFeeController;
use App\Http\Controllers\Admin\MaintenanceRequestController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PaymentMethodController;
use App\Http\Controllers\Admin\PaymentPlanController;
use App\Http\Controllers\Admin\ProfileController;
use App\Http\Controllers\Admin\ReceiptController;
use App\Http\Controllers\Admin\ResidentController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\RoomController;
use App\Http\Controllers\Admin\RoomImageController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UtilityController;
use App\Http\Controllers\Admin\UtilityItemController;
use App\Http\Controllers\Admin\UtilityRateController;
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
    Route::get('dashboard', DashboardController::class);

    Route::apiResource('roles', RoleController::class);
    Route::apiResource('users', UserController::class);
    Route::apiResource('profiles', ProfileController::class);
    Route::apiResource('residents', ResidentController::class);
    Route::apiResource('staff', StaffController::class);

    Route::apiResource('buildings', BuildingController::class);
    Route::apiResource('rooms', RoomController::class);
    Route::apiResource('room-images', RoomImageController::class);
    Route::post('room-images/upload', [RoomImageController::class, 'upload']);

    Route::apiResource('payment-plans', PaymentPlanController::class);
    Route::apiResource('charge-types', ChargeTypeController::class);
    Route::apiResource('payment-methods', PaymentMethodController::class);
    Route::apiResource('late-fees', LateFeeController::class);
    Route::apiResource('utility-types', UtilityTypeController::class);
    Route::apiResource('utility-rates', UtilityRateController::class);

    Route::apiResource('contracts', ContractController::class);
    Route::post('contracts/{contract}/submit', [ContractController::class, 'submit']);
    Route::post('contracts/{contract}/approve', [ContractController::class, 'approve']);
    Route::post('contracts/{contract}/reject', [ContractController::class, 'reject']);

    Route::apiResource('utilities', UtilityController::class);
    Route::post('utilities/{utility}/submit', [UtilityController::class, 'submit']);
    Route::post('utilities/{utility}/approve', [UtilityController::class, 'approve']);
    Route::post('utilities/{utility}/reject', [UtilityController::class, 'reject']);
    Route::apiResource('utilities.utility-items', UtilityItemController::class)->shallow();

    Route::apiResource('invoices', InvoiceController::class);
    Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue']);
    Route::post('invoices/generate-from-contract/{contract}', [InvoiceController::class, 'generateFromContract']);
    Route::apiResource('invoices.invoice-items', InvoiceItemController::class)->shallow();

    Route::apiResource('payments', PaymentController::class);
    Route::post('payments/{payment}/approve', [PaymentController::class, 'approve']);
    Route::post('payments/{payment}/reject', [PaymentController::class, 'reject']);
    Route::post('payments/{payment}/proof', [PaymentController::class, 'uploadProof']);

    Route::apiResource('receipts', ReceiptController::class);
    Route::post('receipts/{receipt}/issue', [ReceiptController::class, 'issue']);
    Route::get('receipts/{receipt}/pdf', [ReceiptController::class, 'pdf']);

    Route::apiResource('maintenance-requests', MaintenanceRequestController::class);
    Route::post('maintenance-requests/{maintenance_request}/start', [MaintenanceRequestController::class, 'start']);
    Route::post('maintenance-requests/{maintenance_request}/complete', [MaintenanceRequestController::class, 'complete']);
    Route::post('maintenance-requests/{maintenance_request}/reject', [MaintenanceRequestController::class, 'reject']);
});
