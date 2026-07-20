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
use App\Http\Controllers\Admin\RentContractDraftController;
use App\Http\Controllers\Admin\SaleContractDraftController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\UtilityController;
use App\Http\Controllers\Admin\UtilityItemController;
use App\Http\Controllers\Admin\UtilityRateController;
use App\Http\Controllers\Admin\UtilityTypeController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Customer\CustomerPortalController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function (): void {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
    });
});

Route::middleware(['auth:sanctum', 'role:customer'])->prefix('customer')->group(function (): void {
    Route::get('dashboard', [CustomerPortalController::class, 'dashboard']);
    Route::get('profile', [CustomerPortalController::class, 'showProfile']);
    Route::put('profile', [CustomerPortalController::class, 'updateProfile']);
    Route::get('contracts', [CustomerPortalController::class, 'contracts']);
    Route::get('contracts/{contract}', [CustomerPortalController::class, 'showContract']);
    Route::get('contracts/{contract}/document/download', [CustomerPortalController::class, 'downloadContractDocument']);
    Route::get('invoices', [CustomerPortalController::class, 'invoices']);
    Route::get('invoices/{invoice}', [CustomerPortalController::class, 'showInvoice']);
    Route::get('invoices/{invoice}/document/download', [CustomerPortalController::class, 'downloadInvoiceDocument']);
    Route::get('payments', [CustomerPortalController::class, 'payments']);
    Route::post('payments', [CustomerPortalController::class, 'storePayment']);
    Route::post('payments/{payment}/proof', [CustomerPortalController::class, 'uploadPaymentProof']);
    Route::get('receipts', [CustomerPortalController::class, 'receipts']);
    Route::get('receipts/{receipt}', [CustomerPortalController::class, 'showReceipt']);
    Route::get('receipts/{receipt}/document/download', [CustomerPortalController::class, 'downloadReceiptDocument']);
    Route::get('notifications', [CustomerPortalController::class, 'notifications']);
    Route::get('payment-methods', [CustomerPortalController::class, 'paymentMethods']);
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

    Route::get('sale-contract-drafts/{sale_contract_draft}/document/download', [SaleContractDraftController::class, 'downloadDocument']);
    Route::get('sale-contract-drafts/{sale_contract_draft}/document/export', [SaleContractDraftController::class, 'exportDocument']);
    Route::post('sale-contract-drafts/{sale_contract_draft}/document/email', [SaleContractDraftController::class, 'sendDocumentEmail']);
    Route::apiResource('sale-contract-drafts', SaleContractDraftController::class);
    Route::post('sale-contract-drafts/{sale_contract_draft}/approve', [SaleContractDraftController::class, 'approve']);
    Route::post('sale-contract-drafts/{sale_contract_draft}/reject', [SaleContractDraftController::class, 'reject']);
    Route::get('sale-contracts/approved', [SaleContractDraftController::class, 'approvedIndex']);
    Route::get('sale-contracts/approved/{sale_contract}/document/download', [SaleContractDraftController::class, 'downloadApprovedDocument']);
    Route::get('sale-contracts/approved/{sale_contract}/document/export', [SaleContractDraftController::class, 'exportApprovedDocument']);
    Route::post('sale-contracts/approved/{sale_contract}/document/email', [SaleContractDraftController::class, 'sendApprovedDocumentEmail']);
    Route::get('sale-contracts/approved/{sale_contract}', [SaleContractDraftController::class, 'approvedShow']);

    Route::get('rent-contract-drafts/{rent_contract_draft}/document/download', [RentContractDraftController::class, 'downloadDocument']);
    Route::get('rent-contract-drafts/{rent_contract_draft}/document/export', [RentContractDraftController::class, 'exportDocument']);
    Route::post('rent-contract-drafts/{rent_contract_draft}/document/email', [RentContractDraftController::class, 'sendDocumentEmail']);
    Route::apiResource('rent-contract-drafts', RentContractDraftController::class);
    Route::post('rent-contract-drafts/{rent_contract_draft}/approve', [RentContractDraftController::class, 'approve']);
    Route::post('rent-contract-drafts/{rent_contract_draft}/reject', [RentContractDraftController::class, 'reject']);
    Route::get('rent-contracts/active', [RentContractDraftController::class, 'activeIndex']);
    Route::get('rent-contracts/active/{rent_contract}/document/download', [RentContractDraftController::class, 'downloadActiveDocument']);
    Route::get('rent-contracts/active/{rent_contract}/document/export', [RentContractDraftController::class, 'exportActiveDocument']);
    Route::post('rent-contracts/active/{rent_contract}/document/email', [RentContractDraftController::class, 'sendActiveDocumentEmail']);
    Route::get('rent-contracts/active/{rent_contract}', [RentContractDraftController::class, 'activeShow']);

    Route::apiResource('contracts', ContractController::class);
    Route::post('contracts/{contract}/submit', [ContractController::class, 'submit']);
    Route::post('contracts/{contract}/approve', [ContractController::class, 'approve']);
    Route::post('contracts/{contract}/reject', [ContractController::class, 'reject']);

    Route::apiResource('utilities', UtilityController::class);
    Route::post('utilities/{utility}/submit', [UtilityController::class, 'submit']);
    Route::post('utilities/{utility}/approve', [UtilityController::class, 'approve']);
    Route::post('utilities/{utility}/reject', [UtilityController::class, 'reject']);
    Route::get('utilities/{utility}/document/download', [UtilityController::class, 'downloadDocument']);
    Route::get('utilities/{utility}/document/export', [UtilityController::class, 'exportDocument']);
    Route::post('utilities/{utility}/document/email', [UtilityController::class, 'sendDocumentEmail']);
    Route::apiResource('utilities.utility-items', UtilityItemController::class)->shallow();

    Route::apiResource('invoices', InvoiceController::class);
    Route::get('invoices/{invoice}/document/download', [InvoiceController::class, 'downloadDocument']);
    Route::get('invoices/{invoice}/document/export', [InvoiceController::class, 'exportDocument']);
    Route::post('invoices/{invoice}/document/email', [InvoiceController::class, 'sendDocumentEmail']);
    Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue']);
    Route::post('invoices/generate-from-contract/{contract}', [InvoiceController::class, 'generateFromContract']);
    Route::apiResource('invoices.invoice-items', InvoiceItemController::class)->shallow();

    Route::apiResource('payments', PaymentController::class);
    Route::post('payments/{payment}/approve', [PaymentController::class, 'approve']);
    Route::post('payments/{payment}/reject', [PaymentController::class, 'reject']);
    Route::post('payments/{payment}/proof', [PaymentController::class, 'uploadProof']);
    Route::get('payments/{payment}/document/download', [PaymentController::class, 'downloadDocument']);
    Route::get('payments/{payment}/document/export', [PaymentController::class, 'exportDocument']);
    Route::post('payments/{payment}/document/email', [PaymentController::class, 'sendDocumentEmail']);

    Route::apiResource('receipts', ReceiptController::class);
    Route::get('receipts/{receipt}/document/download', [ReceiptController::class, 'downloadDocument']);
    Route::get('receipts/{receipt}/document/export', [ReceiptController::class, 'exportDocument']);
    Route::post('receipts/{receipt}/document/email', [ReceiptController::class, 'sendDocumentEmail']);
    Route::post('receipts/{receipt}/issue', [ReceiptController::class, 'issue']);
    Route::get('receipts/{receipt}/pdf', [ReceiptController::class, 'pdf']);

    Route::apiResource('maintenance-requests', MaintenanceRequestController::class);
    Route::post('maintenance-requests/{maintenance_request}/start', [MaintenanceRequestController::class, 'start']);
    Route::post('maintenance-requests/{maintenance_request}/complete', [MaintenanceRequestController::class, 'complete']);
    Route::post('maintenance-requests/{maintenance_request}/reject', [MaintenanceRequestController::class, 'reject']);
});
