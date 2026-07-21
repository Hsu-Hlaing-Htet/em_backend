<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendBillingDocumentRequest;
use App\Http\Resources\Admin\ReceiptResource;
use App\Models\Receipt;
use App\Services\ReceiptDocumentService;
use App\Services\ReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

class ReceiptController extends Controller
{
    public function index(Request $request, ReceiptService $receiptService): JsonResponse
    {
        $paginator = $receiptService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => ReceiptResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function show(Receipt $receipt): JsonResponse
    {
        $receipt->load([
            'payment.invoice.contract.user.profile',
            'payment.invoice.contract.room.building',
            'payment.paymentMethod',
            'creator',
            'approver',
        ]);

        return response()->json([
            'data' => new ReceiptResource($receipt),
        ]);
    }

    public function issue(Receipt $receipt, ReceiptService $receiptService): JsonResponse
    {
        try {
            $receipt = $receiptService->issue($receipt);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        $receipt->load([
            'payment.invoice.contract.user.profile',
            'payment.invoice.contract.room.building',
            'payment.paymentMethod',
            'creator',
            'approver',
        ]);

        return response()->json([
            'message' => 'Receipt issued and sent to customer successfully.',
            'data' => new ReceiptResource($receipt),
        ]);
    }

    public function downloadDocument(Receipt $receipt, ReceiptDocumentService $receiptDocumentService): Response
    {
        return $receiptDocumentService->downloadResponse($receiptDocumentService->find($receipt->id));
    }

    public function exportDocument(Receipt $receipt, ReceiptDocumentService $receiptDocumentService): Response
    {
        return $receiptDocumentService->exportResponse($receiptDocumentService->find($receipt->id));
    }

    public function sendDocumentEmail(
        SendBillingDocumentRequest $request,
        Receipt $receipt,
        ReceiptDocumentService $receiptDocumentService,
    ): JsonResponse {
        try {
            $receiptDocumentService->sendEmail(
                $receiptDocumentService->find($receipt->id),
                $request->validated(),
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Receipt document sent successfully.',
        ]);
    }
}
