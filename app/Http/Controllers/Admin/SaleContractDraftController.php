<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectSaleContractDraftRequest;
use App\Http\Requests\Admin\SendSaleContractDocumentRequest;
use App\Http\Requests\Admin\StoreSaleContractDraftRequest;
use App\Http\Requests\Admin\UpdateSaleContractDraftRequest;
use App\Http\Resources\Admin\ContractResource;
use App\Models\Contract;
use App\Services\SaleContractDocumentService;
use App\Services\SaleContractDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

class SaleContractDraftController extends Controller
{
    public function index(Request $request, SaleContractDraftService $saleContractDraftService): JsonResponse
    {
        $paginator = $saleContractDraftService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => ContractResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreSaleContractDraftRequest $request, SaleContractDraftService $saleContractDraftService): JsonResponse
    {
        try {
            $contract = $saleContractDraftService->create($request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Sale contract draft created successfully.',
            'data' => new ContractResource($contract),
        ], 201);
    }

    public function show(int $sale_contract_draft, SaleContractDraftService $saleContractDraftService): JsonResponse
    {
        $contract = $saleContractDraftService->find($sale_contract_draft);

        return response()->json([
            'data' => new ContractResource($contract),
        ]);
    }

    public function update(
        UpdateSaleContractDraftRequest $request,
        int $sale_contract_draft,
        SaleContractDraftService $saleContractDraftService,
    ): JsonResponse {
        try {
            $contract = $saleContractDraftService->find($sale_contract_draft);
            $contract = $saleContractDraftService->update($contract, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Sale contract draft updated successfully.',
            'data' => new ContractResource($contract),
        ]);
    }

    public function destroy(int $sale_contract_draft, SaleContractDraftService $saleContractDraftService): JsonResponse
    {
        try {
            $contract = $saleContractDraftService->find($sale_contract_draft);
            $saleContractDraftService->delete($contract);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Sale contract draft deleted successfully.',
        ]);
    }

    public function approvedIndex(Request $request, SaleContractDraftService $saleContractDraftService): JsonResponse
    {
        $paginator = $saleContractDraftService->paginateApproved($request->all());

        return response()->json([
            'data' => [
                'data' => ContractResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function approvedShow(int $sale_contract, SaleContractDraftService $saleContractDraftService): JsonResponse
    {
        $contract = $saleContractDraftService->findApproved($sale_contract);

        return response()->json([
            'data' => new ContractResource($contract),
        ]);
    }

    public function approve(int $sale_contract_draft, SaleContractDraftService $saleContractDraftService): JsonResponse
    {
        try {
            $contract = $saleContractDraftService->find($sale_contract_draft);
            $contract = $saleContractDraftService->approve($contract);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Sale contract approved successfully.',
            'data' => new ContractResource($contract),
        ]);
    }

    public function reject(
        RejectSaleContractDraftRequest $request,
        int $sale_contract_draft,
        SaleContractDraftService $saleContractDraftService,
    ): JsonResponse {
        try {
            $contract = $saleContractDraftService->find($sale_contract_draft);
            $contract = $saleContractDraftService->reject(
                $contract,
                $request->validated()['rejection_reason'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Sale contract rejected successfully.',
            'data' => new ContractResource($contract),
        ]);
    }

    public function downloadDocument(
        int $sale_contract_draft,
        SaleContractDocumentService $saleContractDocumentService,
    ): Response {
        $contract = $saleContractDocumentService->findDraft($sale_contract_draft);

        return $saleContractDocumentService->downloadResponse($contract);
    }

    public function exportDocument(
        int $sale_contract_draft,
        SaleContractDocumentService $saleContractDocumentService,
    ): Response {
        $contract = $saleContractDocumentService->findDraft($sale_contract_draft);

        return $saleContractDocumentService->exportResponse($contract);
    }

    public function sendDocumentEmail(
        SendSaleContractDocumentRequest $request,
        int $sale_contract_draft,
        SaleContractDocumentService $saleContractDocumentService,
    ): JsonResponse {
        try {
            $contract = $saleContractDocumentService->findDraft($sale_contract_draft);
            $saleContractDocumentService->sendEmail($contract, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Sale contract document sent successfully.',
        ]);
    }

    public function downloadApprovedDocument(
        int $sale_contract,
        SaleContractDocumentService $saleContractDocumentService,
    ): Response {
        $contract = $saleContractDocumentService->findApproved($sale_contract);

        return $saleContractDocumentService->downloadResponse($contract);
    }

    public function exportApprovedDocument(
        int $sale_contract,
        SaleContractDocumentService $saleContractDocumentService,
    ): Response {
        $contract = $saleContractDocumentService->findApproved($sale_contract);

        return $saleContractDocumentService->exportResponse($contract);
    }

    public function sendApprovedDocumentEmail(
        SendSaleContractDocumentRequest $request,
        int $sale_contract,
        SaleContractDocumentService $saleContractDocumentService,
    ): JsonResponse {
        try {
            $contract = $saleContractDocumentService->findApproved($sale_contract);
            $saleContractDocumentService->sendEmail($contract, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Sale contract document sent successfully.',
        ]);
    }
}
