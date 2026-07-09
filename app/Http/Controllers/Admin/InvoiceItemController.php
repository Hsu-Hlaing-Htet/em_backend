<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreInvoiceItemRequest;
use App\Http\Requests\Admin\UpdateInvoiceItemRequest;
use App\Http\Resources\Admin\InvoiceItemResource;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;

class InvoiceItemController extends Controller
{
    public function index(Invoice $invoice): JsonResponse
    {
        $invoice->load('items.chargeType');

        return response()->json([
            'data' => InvoiceItemResource::collection($invoice->items)->resolve(),
        ]);
    }

    public function store(StoreInvoiceItemRequest $request, Invoice $invoice, InvoiceService $invoiceService): JsonResponse
    {
        if ($invoice->status !== 'draft') {
            return response()->json(['message' => 'Only draft invoices can be updated.'], 422);
        }

        $item = $invoiceService->createItem($invoice, $request->validated());

        return response()->json([
            'message' => 'Invoice item created successfully.',
            'data' => new InvoiceItemResource($item),
        ], 201);
    }

    public function show(InvoiceItem $invoiceItem): JsonResponse
    {
        $invoiceItem->load('chargeType');

        return response()->json([
            'data' => new InvoiceItemResource($invoiceItem),
        ]);
    }

    public function update(UpdateInvoiceItemRequest $request, InvoiceItem $invoiceItem, InvoiceService $invoiceService): JsonResponse
    {
        if ($invoiceItem->invoice->status !== 'draft') {
            return response()->json(['message' => 'Only draft invoices can be updated.'], 422);
        }

        $item = $invoiceService->updateItem($invoiceItem, $request->validated());

        return response()->json([
            'message' => 'Invoice item updated successfully.',
            'data' => new InvoiceItemResource($item),
        ]);
    }

    public function destroy(InvoiceItem $invoiceItem, InvoiceService $invoiceService): JsonResponse
    {
        if ($invoiceItem->invoice->status !== 'draft') {
            return response()->json(['message' => 'Only draft invoices can be updated.'], 422);
        }

        $invoiceService->deleteItem($invoiceItem);

        return response()->json([
            'message' => 'Invoice item deleted successfully.',
        ]);
    }
}
