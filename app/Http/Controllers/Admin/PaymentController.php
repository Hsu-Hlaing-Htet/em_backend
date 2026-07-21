<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendBillingDocumentRequest;
use App\Http\Requests\Admin\StorePaymentRequest;
use App\Http\Requests\Admin\UpdatePaymentRequest;
use App\Http\Requests\Admin\UploadPaymentProofRequest;
use App\Http\Resources\Admin\PaymentResource;
use App\Models\Payment;
use App\Services\PaymentDocumentService;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        }

        $payment->load([
            'invoice.contract.user.profile',
            'invoice.contract.room.building',
            'paymentMethod',
            'receipt',
        ]);

        return response()->json([
            'message' => 'Payment created successfully.',
            'data' => new PaymentResource($payment),
        ], 201);
    }

    public function show(Payment $payment): JsonResponse
    {
        $payment->load([
            'invoice.contract.user.profile',
            'invoice.contract.room.building',
            'paymentMethod',
            'creator',
            'approver',
            'receipt',
        ]);

        return response()->json([
            'data' => new PaymentResource($payment),
        ]);
    }

    public function update(
        UpdatePaymentRequest $request,
        Payment $payment,
        PaymentService $paymentService,
    ): JsonResponse {
        try {
            $payment = $paymentService->update($payment, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
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
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Payment deleted successfully.',
        ]);
    }

    public function approve(Payment $payment, PaymentService $paymentService): JsonResponse
    {
        try {
            $payment = $paymentService->approve($payment);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $payment->load([
            'invoice.contract.user.profile',
            'invoice.contract.room.building',
            'paymentMethod',
            'creator',
            'approver',
            'receipt',
        ]);

        return response()->json([
            'message' => 'Payment approved successfully. A draft receipt has been created for review.',
            'data' => new PaymentResource($payment),
        ]);
    }

    public function reject(Payment $payment, PaymentService $paymentService): JsonResponse
    {
        $payment = $paymentService->reject($payment);

        $payment->load([
            'invoice.contract.user.profile',
            'invoice.contract.room.building',
            'paymentMethod',
            'creator',
            'approver',
            'receipt',
        ]);

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
        $payment = $paymentService->uploadProof($payment, $request->file('proof'));

        $payment->load([
            'invoice.contract.user.profile',
            'invoice.contract.room.building',
            'paymentMethod',
            'creator',
            'approver',
            'receipt',
        ]);

        return response()->json([
            'message' => 'Payment proof uploaded successfully.',
            'data' => new PaymentResource($payment),
        ]);
    }

    public function downloadDocument(Payment $payment, PaymentDocumentService $paymentDocumentService): Response
    {
        return $paymentDocumentService->downloadResponse($paymentDocumentService->find($payment->id));
    }

    public function exportDocument(Payment $payment, PaymentDocumentService $paymentDocumentService): Response
    {
        return $paymentDocumentService->exportResponse($paymentDocumentService->find($payment->id));
    }

    public function sendDocumentEmail(
        SendBillingDocumentRequest $request,
        Payment $payment,
        PaymentDocumentService $paymentDocumentService,
    ): JsonResponse {
        try {
            $paymentDocumentService->sendEmail(
                $paymentDocumentService->find($payment->id),
                $request->validated(),
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Payment confirmation document sent successfully.',
        ]);
    }
}
