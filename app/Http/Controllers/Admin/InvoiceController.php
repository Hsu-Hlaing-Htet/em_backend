<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SendBillingDocumentRequest;
use App\Http\Requests\Admin\StoreInvoiceRequest;
use App\Http\Requests\Admin\UpdateInvoiceRequest;
use App\Http\Resources\Admin\InvoiceResource;
use App\Models\Contract;
use App\Models\Invoice;
use App\Services\InvoiceDocumentService;
use App\Services\InvoiceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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

    public function show(Invoice $invoice, InvoiceService $invoiceService): JsonResponse
    {
        return response()->json([
            'data' => new InvoiceResource($invoiceService->find($invoice->id)),
        ]);
    }

    public function update(
        UpdateInvoiceRequest $request,
        Invoice $invoice,
        InvoiceService $invoiceService,
    ): JsonResponse {
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
        try {
            $invoiceService->delete($invoice);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Invoice deleted successfully.',
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

    public function issue(Invoice $invoice, InvoiceService $invoiceService): JsonResponse
    {
        try {
            $invoice = $invoiceService->issue($invoice);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Invoice issued and sent to customer successfully.',
            'data' => new InvoiceResource($invoice),
        ]);
    }

    public function downloadDocument(Invoice $invoice, InvoiceDocumentService $invoiceDocumentService): Response
    {
        return $invoiceDocumentService->downloadResponse($invoiceDocumentService->find($invoice->id));
    }

    public function exportDocument(Invoice $invoice, InvoiceDocumentService $invoiceDocumentService): Response
    {
        return $invoiceDocumentService->exportResponse($invoiceDocumentService->find($invoice->id));
    }

    public function sendDocumentEmail(
        SendBillingDocumentRequest $request,
        Invoice $invoice,
        InvoiceDocumentService $invoiceDocumentService,
    ): JsonResponse {
        try {
            $invoiceDocumentService->sendEmail(
                $invoiceDocumentService->find($invoice->id),
                $request->validated(),
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Invoice document sent successfully.',
        ]);
    }
}
