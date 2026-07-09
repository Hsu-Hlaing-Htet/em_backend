<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreReceiptRequest;
use App\Http\Requests\Admin\UpdateReceiptRequest;
use App\Http\Resources\Admin\ReceiptResource;
use App\Models\Receipt;
use App\Services\ReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function store(StoreReceiptRequest $request, ReceiptService $receiptService): JsonResponse
    {
        try {
            $receipt = $receiptService->create($request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Receipt created successfully.',
            'data' => new ReceiptResource($receipt),
        ], 201);
    }

    public function show(Receipt $receipt): JsonResponse
    {
        $receipt->load(['payment.invoice.contract.user', 'payment.paymentMethod', 'creator', 'approver']);

        return response()->json([
            'data' => new ReceiptResource($receipt),
        ]);
    }

    public function update(UpdateReceiptRequest $request, Receipt $receipt, ReceiptService $receiptService): JsonResponse
    {
        try {
            $receipt = $receiptService->update($receipt, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Receipt updated successfully.',
            'data' => new ReceiptResource($receipt),
        ]);
    }

    public function destroy(Receipt $receipt, ReceiptService $receiptService): JsonResponse
    {
        $receiptService->delete($receipt);

        return response()->json([
            'message' => 'Receipt deleted successfully.',
        ]);
    }

    public function issue(Receipt $receipt, ReceiptService $receiptService): JsonResponse
    {
        try {
            $receipt = $receiptService->issue($receipt);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Receipt issued successfully.',
            'data' => new ReceiptResource($receipt),
        ]);
    }

    public function pdf(Receipt $receipt, ReceiptService $receiptService)
    {
        $receipt = $receiptService->find($receipt->id);

        if (! $receipt->receipt_pdf_path || ! \Illuminate\Support\Facades\Storage::disk('public')->exists($receipt->receipt_pdf_path)) {
            return response()->json(['message' => 'Receipt PDF not found.'], 404);
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->download(
            $receipt->receipt_pdf_path,
            $receipt->receipt_number.'.html'
        );
    }
}
