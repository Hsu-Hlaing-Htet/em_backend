<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Payment\StorePaymentRequest;
use App\Http\Requests\Payment\UpdatePaymentRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Payment::query()
            ->with(['invoice', 'receipt', 'recordedBy'])
            ->latest('payment_date');

        if ($request->filled('invoice_id')) {
            $query->where('invoice_id', $request->integer('invoice_id'));
        }

        if ($request->filled('payment_method')) {
            $query->where('payment_method', $request->string('payment_method'));
        }

        if ($request->filled('from')) {
            $query->whereDate('payment_date', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->whereDate('payment_date', '<=', $request->date('to'));
        }

        return response()->json(
            $query->paginate((int) $request->integer('per_page', 15))
        );
    }

    public function show(Payment $payment): JsonResponse
    {
        return response()->json($payment->load(['invoice.property', 'receipt', 'recordedBy']));
    }

    public function store(StorePaymentRequest $request, Invoice $invoice): JsonResponse
    {
        $payment = $this->paymentService->record($invoice, [
            ...$request->validated(),
            'recorded_by_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Payment recorded successfully.',
            'data' => $payment,
        ], 201);
    }

    public function update(UpdatePaymentRequest $request, Payment $payment): JsonResponse
    {
        $updatedPayment = $this->paymentService->update($payment, [
            ...$request->validated(),
            'recorded_by_user_id' => $request->user()?->id,
        ]);

        return response()->json([
            'message' => 'Payment updated successfully.',
            'data' => $updatedPayment,
        ]);
    }

    public function destroy(Payment $payment): JsonResponse
    {
        $this->paymentService->delete($payment);

        return response()->json([
            'message' => 'Payment deleted successfully.',
        ]);
    }
}
