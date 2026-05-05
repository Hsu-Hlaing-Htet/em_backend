<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Models\Invoice;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MyInvoiceController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json(
            Invoice::query()
                ->where('user_id', $request->user()->id)
                ->with(['property', 'payments.receipt'])
                ->latest('id')
                ->paginate(15)
        );
    }

    public function show(Request $request, Invoice $invoice): JsonResponse
    {
        abort_unless((int) $invoice->user_id === (int) $request->user()->id, 403);

        return response()->json($invoice->load(['property', 'items', 'payments.receipt']));
    }

    public function pay(StorePaymentRequest $request, Invoice $invoice): JsonResponse
    {
        abort_unless((int) $invoice->user_id === (int) $request->user()->id, 403);

        $payment = $this->paymentService->record($invoice, [
            ...$request->validated(),
            'recorded_by_user_id' => $request->user()->id,
        ]);

        return response()->json([
            'message' => 'Payment submitted successfully.',
            'data' => $payment,
        ], 201);
    }
}
