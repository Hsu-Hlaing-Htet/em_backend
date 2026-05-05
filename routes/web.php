<?php

use App\Http\Controllers\Admin\ContractController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\MeterReadingController;
use App\Http\Controllers\Admin\OwnerController;
use App\Http\Controllers\Admin\PaymentController;
use App\Http\Controllers\Admin\PropertyController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\TenantController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Owner\DashboardController as OwnerDashboardController;
use App\Http\Controllers\Owner\MyInvoiceController;
use App\Http\Controllers\Owner\MyPaymentController;
use App\Http\Controllers\Owner\MyPropertyController;
use App\Http\Controllers\Public\PropertyListingController;
use App\Http\Controllers\Public\ViewingRequestController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('api/public')->group(function (): void {
    Route::get('/properties', [PropertyListingController::class, 'index']);
    Route::get('/properties/featured', [PropertyListingController::class, 'featured']);
    Route::get('/properties/stats', [PropertyListingController::class, 'stats']);
    Route::get('/properties/{property}', [PropertyListingController::class, 'show']);
    Route::post('/viewing-requests', [ViewingRequestController::class, 'store']);
    Route::post('/contact', function (Request $request) {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email'],
            'phone' => ['nullable', 'string', 'max:50'],
            'message' => ['required', 'string'],
        ]);

        return response()->json([
            'message' => 'Thank you for contacting Rosewood Royale. We will get back to you shortly.',
            'data' => $validated,
        ], 201);
    });
});

Route::prefix('api/auth')->group(function (): void {
    Route::post('/register', [AuthController::class, 'register'])->middleware('guest');
    Route::post('/login', [AuthController::class, 'login'])->middleware('guest');
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
});

Route::middleware(['auth', 'role:admin'])->prefix('api/admin')->group(function (): void {
    Route::get('/dashboard', AdminDashboardController::class);

    Route::apiResource('properties', PropertyController::class);

    Route::get('/owners', [OwnerController::class, 'index']);
    Route::get('/tenants', [TenantController::class, 'index']);

    Route::get('/contracts', [ContractController::class, 'index']);
    Route::get('/contracts/{contract}', [ContractController::class, 'show']);

    Route::post('/invoices/generate', [InvoiceController::class, 'generate']);
    Route::get('/invoices', [InvoiceController::class, 'index']);
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
    Route::post('/invoices/{invoice}/send', [InvoiceController::class, 'send']);

    Route::post('/invoices/{invoice}/payments', [PaymentController::class, 'store']);
    Route::get('/payments', [PaymentController::class, 'index']);
    Route::get('/payments/{payment}', [PaymentController::class, 'show']);
    Route::put('/payments/{payment}', [PaymentController::class, 'update']);
    Route::patch('/payments/{payment}', [PaymentController::class, 'update']);
    Route::delete('/payments/{payment}', [PaymentController::class, 'destroy']);

    Route::get('/meter-readings', [MeterReadingController::class, 'index']);
    Route::post('/meter-readings', [MeterReadingController::class, 'store']);

    Route::get('/reports/monthly-payments', [ReportController::class, 'monthlyPayments']);
    Route::get('/reports/unpaid-invoices', [ReportController::class, 'unpaidInvoices']);
    Route::get('/reports/occupancy', [ReportController::class, 'occupancy']);
    Route::get('/reports/utility-usage', [ReportController::class, 'utilityUsage']);
});

Route::middleware(['auth', 'role:owner'])->prefix('api/owner')->group(function (): void {
    Route::get('/dashboard', OwnerDashboardController::class);
    Route::get('/my-properties', [MyPropertyController::class, 'index']);

    Route::get('/my-invoices', [MyInvoiceController::class, 'index']);
    Route::get('/my-invoices/{invoice}', [MyInvoiceController::class, 'show']);
    Route::post('/my-invoices/{invoice}/pay', [MyInvoiceController::class, 'pay']);

    Route::get('/my-payments', [MyPaymentController::class, 'index']);
    Route::get('/receipts/{receipt}', [MyPaymentController::class, 'receipt']);
});

Route::view('/{path?}', 'app')
    ->where('path', '^(?!api|up).*$');
