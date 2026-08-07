<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\StoreCustomerMaintenanceRequestRequest;
use App\Http\Requests\Customer\StoreCustomerPaymentRequest;
use App\Http\Requests\Customer\UpdateCustomerProfileRequest;
use App\Http\Resources\Admin\ContractResource;
use App\Http\Resources\Admin\InvoiceResource;
use App\Http\Resources\Admin\MaintenanceRequestResource;
use App\Http\Resources\Admin\PaymentResource;
use App\Http\Resources\Admin\ReceiptResource;
use App\Http\Resources\Admin\ResidentResource;
use App\Models\Contract;
use App\Models\Invoice;
use App\Models\MaintenanceRequest;
use App\Models\Payment;
use App\Models\Receipt;
use App\Services\CustomerPortalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class CustomerPortalController extends Controller
{
    /**
     * @return array<string, mixed>
     */
    private function mapPayment(Payment $payment): array
    {
        $resource = (new PaymentResource($payment))->resolve();
        $receipt = $payment->relationLoaded('receipt')
            ? $payment->receipt
            : $payment->receipt()->first();

        $resource['receipt_id'] = $receipt?->isDeliveredToCustomer() ? $receipt->id : null;

        return $resource;
    }

    public function dashboard(Request $request, CustomerPortalService $customerPortalService): JsonResponse
    {
        return response()->json([
            'data' => $customerPortalService->dashboardSummary($request->user()),
        ]);
    }

    public function showProfile(Request $request, CustomerPortalService $customerPortalService): JsonResponse
    {
        $user = $customerPortalService->profile($request->user());

        return response()->json([
            'data' => new ResidentResource($user),
        ]);
    }

    public function updateProfile(
        UpdateCustomerProfileRequest $request,
        CustomerPortalService $customerPortalService,
    ): JsonResponse {
        $user = $customerPortalService->updateProfile($request->user(), $request->validated());

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data' => new ResidentResource($user),
        ]);
    }

    public function contracts(Request $request, CustomerPortalService $customerPortalService): JsonResponse
    {
        $paginator = $customerPortalService->paginateContracts($request->user(), $request->all());

        return response()->json([
            'data' => [
                'data' => ContractResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function showContract(
        Request $request,
        Contract $contract,
        CustomerPortalService $customerPortalService,
    ): JsonResponse {
        $contract = $customerPortalService->findContract($request->user(), $contract->id);

        return response()->json([
            'data' => new ContractResource($contract),
        ]);
    }

    public function downloadContractDocument(
        Request $request,
        Contract $contract,
        CustomerPortalService $customerPortalService,
    ) {
        return $customerPortalService->contractDocumentResponse($request->user(), $contract, 'download');
    }

    public function invoices(Request $request, CustomerPortalService $customerPortalService): JsonResponse
    {
        $paginator = $customerPortalService->paginateInvoices($request->user(), $request->all());

        $items = collect($paginator->items())->map(function (Invoice $invoice) use ($customerPortalService) {
            $resource = (new InvoiceResource($invoice))->resolve();
            $resource['paid_amount'] = $customerPortalService->invoicePaidAmount($invoice);

            return $resource;
        });

        return response()->json([
            'data' => [
                'data' => $items,
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function showInvoice(
        Request $request,
        Invoice $invoice,
        CustomerPortalService $customerPortalService,
    ): JsonResponse {
        $invoice = $customerPortalService->findInvoice($request->user(), $invoice->id);
        $resource = (new InvoiceResource($invoice))->resolve();
        $resource['paid_amount'] = $customerPortalService->invoicePaidAmount($invoice);
        $resource['payments'] = collect($invoice->payments)
            ->map(fn (Payment $payment) => $this->mapPayment($payment))
            ->values()
            ->all();

        return response()->json([
            'data' => $resource,
        ]);
    }

    public function downloadInvoiceDocument(
        Request $request,
        Invoice $invoice,
        CustomerPortalService $customerPortalService,
    ) {
        return $customerPortalService->invoiceDocumentResponse($request->user(), $invoice, 'download');
    }

    public function payments(Request $request, CustomerPortalService $customerPortalService): JsonResponse
    {
        $paginator = $customerPortalService->paginatePayments($request->user(), $request->all());

        $items = collect($paginator->items())->map(fn (Payment $payment) => $this->mapPayment($payment));

        return response()->json([
            'data' => [
                'data' => $items,
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function storePayment(
        StoreCustomerPaymentRequest $request,
        CustomerPortalService $customerPortalService,
    ): JsonResponse {
        try {
            $payment = $customerPortalService->submitPayment(
                $request->user(),
                $request->safe()->except(['proof']),
                $request->file('proof'),
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Payment submitted successfully.',
            'data' => new PaymentResource($payment),
        ], 201);
    }

    public function uploadPaymentProof(
        Request $request,
        Payment $payment,
        CustomerPortalService $customerPortalService,
    ): JsonResponse {
        $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:5120'],
        ]);

        $payment = $customerPortalService->uploadPaymentProof(
            $request->user(),
            $payment->id,
            $request->file('proof'),
        );

        return response()->json([
            'message' => 'Payment proof uploaded successfully.',
            'data' => new PaymentResource($payment),
        ]);
    }

    public function receipts(Request $request, CustomerPortalService $customerPortalService): JsonResponse
    {
        $paginator = $customerPortalService->paginateReceipts($request->user(), $request->all());

        return response()->json([
            'data' => [
                'data' => ReceiptResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function showReceipt(
        Request $request,
        Receipt $receipt,
        CustomerPortalService $customerPortalService,
    ): JsonResponse {
        $receipt = $customerPortalService->findReceipt($request->user(), $receipt->id);

        return response()->json([
            'data' => new ReceiptResource($receipt),
        ]);
    }

    public function downloadReceiptDocument(
        Request $request,
        Receipt $receipt,
        CustomerPortalService $customerPortalService,
    ) {
        return $customerPortalService->receiptDocumentResponse($request->user(), $receipt, 'download');
    }

    public function notifications(Request $request, CustomerPortalService $customerPortalService): JsonResponse
    {
        return response()->json([
            'data' => $customerPortalService->notifications($request->user()),
        ]);
    }

    public function paymentMethods(CustomerPortalService $customerPortalService): JsonResponse
    {
        return response()->json([
            'data' => $customerPortalService->paymentMethods(),
        ]);
    }

    public function maintenanceRooms(Request $request, CustomerPortalService $customerPortalService): JsonResponse
    {
        return response()->json([
            'data' => $customerPortalService->maintenanceRooms($request->user()),
        ]);
    }

    public function maintenanceRequests(Request $request, CustomerPortalService $customerPortalService): JsonResponse
    {
        $paginator = $customerPortalService->paginateMaintenanceRequests($request->user(), $request->all());

        return response()->json([
            'data' => [
                'data' => MaintenanceRequestResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function showMaintenanceRequest(
        Request $request,
        MaintenanceRequest $maintenanceRequest,
        CustomerPortalService $customerPortalService,
    ): JsonResponse {
        $maintenanceRequest = $customerPortalService->findMaintenanceRequest(
            $request->user(),
            $maintenanceRequest->id,
        );

        return response()->json([
            'data' => new MaintenanceRequestResource($maintenanceRequest),
        ]);
    }

    public function storeMaintenanceRequest(
        StoreCustomerMaintenanceRequestRequest $request,
        CustomerPortalService $customerPortalService,
    ): JsonResponse {
        try {
            $maintenanceRequest = $customerPortalService->createMaintenanceRequest(
                $request->user(),
                $request->validated(),
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Maintenance request submitted successfully.',
            'data' => new MaintenanceRequestResource($maintenanceRequest),
        ], 201);
    }
}
