<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInvoiceRequest;
use App\Http\Requests\Admin\UpdateInvoiceRequest;
use App\Http\Resources\Admin\InvoiceResource;
use App\Models\Contract;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class InvoiceController extends Controller
{
    public function index(Request $request, InvoiceService $invoiceService): JsonResponse
    {
        $paginator = $invoiceService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => InvoiceResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreInvoiceRequest $request, InvoiceService $invoiceService): JsonResponse
    {
        $invoice = $invoiceService->create($request->validated());

        return response()->json([
            'message' => 'Invoice created successfully.',
            'data' => new InvoiceResource($invoice),
        ], 201);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['contract.user', 'contract.room', 'utility', 'items.chargeType', 'payments', 'creator', 'approver']);

        return response()->json([
            'data' => new InvoiceResource($invoice),
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice, InvoiceService $invoiceService): JsonResponse
    {
        try {
            $invoice = $invoiceService->update($invoice, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Invoice updated successfully.',
            'data' => new InvoiceResource($invoice),
        ]);
    }

    public function destroy(Invoice $invoice, InvoiceService $invoiceService): JsonResponse
    {
        $invoiceService->delete($invoice);

        return response()->json([
            'message' => 'Invoice deleted successfully.',
        ]);
    }

    public function issue(Invoice $invoice, InvoiceService $invoiceService): JsonResponse
    {
        try {
            $invoice = $invoiceService->issue($invoice);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Invoice issued successfully.',
            'data' => new InvoiceResource($invoice),
        ]);
    }

    public function generateFromContract(Contract $contract, InvoiceService $invoiceService): JsonResponse
    {
        try {
            $invoice = $invoiceService->generateFromContract($contract);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Invoice generated successfully.',
            'data' => new InvoiceResource($invoice),
        ], 201);
    }
}
