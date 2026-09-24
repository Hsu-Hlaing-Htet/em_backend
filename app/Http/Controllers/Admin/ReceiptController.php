<?php

namespace App\Http\Controllers\Admin;

use App\Exceptions\ConcurrentConflictException;
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

    public function show(Receipt $receipt, ReceiptService $receiptService): JsonResponse
    {
        return response()->json([
            'data' => new ReceiptResource($receiptService->find($receipt->id)),
        ]);
    }

    public function issue(Receipt $receipt, ReceiptService $receiptService): JsonResponse
    {
        try {
            $receipt = $receiptService->issue($receipt);
        } catch (ConcurrentConflictException|InvalidArgumentException $exception) {
            $status = $exception instanceof ConcurrentConflictException ? 409 : 422;

            return response()->json(['message' => $exception->getMessage()], $status);
        }

        return response()->json([
            'message' => 'Receipt issued successfully.',
            'data' => new ReceiptResource($receiptService->find($receipt->id)),
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
        ReceiptService $receiptService,
    ): JsonResponse {
        try {
            $receipt = $receiptService->deliverByEmail(
                $receipt,
                $request->validated(),
            );
        } catch (ConcurrentConflictException|InvalidArgumentException $exception) {
            $status = $exception instanceof ConcurrentConflictException ? 409 : 422;

            return response()->json(['message' => $exception->getMessage()], $status);
        }

        return response()->json([
            'message' => 'Receipt sent to customer successfully.',
            'data' => new ReceiptResource($receipt),
        ]);
    }
}
