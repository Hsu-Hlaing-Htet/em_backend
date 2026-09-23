<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ConcurrentConflictException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ApprovePaymentRequest;
use App\Http\Requests\Admin\RejectPaymentRequest;
use App\Http\Requests\Admin\StorePaymentRequest;
use App\Http\Requests\Admin\UpdatePaymentRequest;
use App\Http\Requests\Admin\UploadPaymentProofRequest;
use App\Http\Resources\Admin\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentService;
use App\Support\BillingEagerLoads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class PaymentController extends Controller
{
    public function index(Request $request, PaymentService $paymentService): JsonResponse
    {
        $paginator = $paymentService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => PaymentResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StorePaymentRequest $request, PaymentService $paymentService): JsonResponse
    {
        $data = $request->validated();
        $proof = $request->file('proof');
        unset($data['proof']);

        $payment = $paymentService->create($data);

        if ($proof) {
            $payment = $paymentService->uploadProof($payment, $proof);
        } else {
            $payment->load(BillingEagerLoads::payment());
        }

        return response()->json([
            'message' => 'Payment created successfully.',
            'data' => new PaymentResource($payment),
        ], 201);
    }

    public function show(Payment $payment, PaymentService $paymentService): JsonResponse
    {
        return response()->json([
            'data' => new PaymentResource($paymentService->find($payment->id)),
        ]);
    }

    public function update(
        UpdatePaymentRequest $request,
        Payment $payment,
        PaymentService $paymentService,
    ): JsonResponse {
        try {
            $payment = $paymentService->update($payment, $request->validated());
        } catch (ConcurrentConflictException|InvalidArgumentException $exception) {
            $status = $exception instanceof ConcurrentConflictException ? 409 : 422;

            return response()->json(['message' => $exception->getMessage()], $status);
        }

        return response()->json([
            'message' => 'Payment updated successfully.',
            'data' => new PaymentResource($payment),
        ]);
    }

    public function destroy(Payment $payment, PaymentService $paymentService): JsonResponse
    {
        try {
            $paymentService->delete($payment);
        } catch (ConcurrentConflictException|InvalidArgumentException $exception) {
            $status = $exception instanceof ConcurrentConflictException ? 409 : 422;

            return response()->json(['message' => $exception->getMessage()], $status);
        }

        return response()->json([
            'message' => 'Payment rejected successfully.',
        ]);
    }

    public function approve(
        ApprovePaymentRequest $request,
        Payment $payment,
        PaymentService $paymentService,
    ): JsonResponse {
        try {
            $payment = $paymentService->approve($payment, $request->validated('amount'));
        } catch (ConcurrentConflictException|InvalidArgumentException $exception) {
            $status = $exception instanceof ConcurrentConflictException ? 409 : 422;

            return response()->json(['message' => $exception->getMessage()], $status);
        }

        return response()->json([
            'message' => 'Payment approved successfully. Receipt is available in the Customer Portal.',
            'data' => new PaymentResource($payment),
        ]);
    }

    public function reject(
        RejectPaymentRequest $request,
        Payment $payment,
        PaymentService $paymentService,
    ): JsonResponse {
        try {
            $payment = $paymentService->reject(
                $payment,
                $request->validated('rejection_reason'),
            );
        } catch (ConcurrentConflictException|InvalidArgumentException $exception) {
            $status = $exception instanceof ConcurrentConflictException ? 409 : 422;

            return response()->json(['message' => $exception->getMessage()], $status);
        }

        return response()->json([
            'message' => 'Payment rejected successfully.',
            'data' => new PaymentResource($payment),
        ]);
    }

    public function uploadProof(
        UploadPaymentProofRequest $request,
        Payment $payment,
        PaymentService $paymentService,
    ): JsonResponse {
        try {
            $payment = $paymentService->uploadProof($payment, $request->file('proof'));
        } catch (ConcurrentConflictException|InvalidArgumentException $exception) {
            $status = $exception instanceof ConcurrentConflictException ? 409 : 422;

            return response()->json(['message' => $exception->getMessage()], $status);
        }

        return response()->json([
            'message' => 'Payment proof uploaded successfully.',
            'data' => new PaymentResource($payment),
        ]);
    }
}
