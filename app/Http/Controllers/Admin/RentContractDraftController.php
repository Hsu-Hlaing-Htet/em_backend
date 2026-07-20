<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectRentContractDraftRequest;
use App\Http\Requests\Admin\SendRentContractDocumentRequest;
use App\Http\Requests\Admin\StoreRentContractDraftRequest;
use App\Http\Requests\Admin\UpdateRentContractDraftRequest;
use App\Http\Resources\Admin\ContractResource;
use App\Services\RentContractDocumentService;
use App\Services\RentContractDraftService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

class RentContractDraftController extends Controller
{
    public function index(Request $request, RentContractDraftService $rentContractDraftService): JsonResponse
    {
        $paginator = $rentContractDraftService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => ContractResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreRentContractDraftRequest $request, RentContractDraftService $rentContractDraftService): JsonResponse
    {
        try {
            $contract = $rentContractDraftService->create($request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Rent contract draft created successfully.',
            'data' => new ContractResource($contract),
        ], 201);
    }

    public function show(int $rent_contract_draft, RentContractDraftService $rentContractDraftService): JsonResponse
    {
        $contract = $rentContractDraftService->find($rent_contract_draft);

        return response()->json([
            'data' => new ContractResource($contract),
        ]);
    }

    public function update(
        UpdateRentContractDraftRequest $request,
        int $rent_contract_draft,
        RentContractDraftService $rentContractDraftService,
    ): JsonResponse {
        try {
            $contract = $rentContractDraftService->find($rent_contract_draft);
            $contract = $rentContractDraftService->update($contract, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Rent contract draft updated successfully.',
            'data' => new ContractResource($contract),
        ]);
    }

    public function destroy(int $rent_contract_draft, RentContractDraftService $rentContractDraftService): JsonResponse
    {
        try {
            $contract = $rentContractDraftService->find($rent_contract_draft);
            $rentContractDraftService->delete($contract);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Rent contract draft deleted successfully.',
        ]);
    }

    public function activeIndex(Request $request, RentContractDraftService $rentContractDraftService): JsonResponse
    {
        $paginator = $rentContractDraftService->paginateActive($request->all());

        return response()->json([
            'data' => [
                'data' => ContractResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function activeShow(int $rent_contract, RentContractDraftService $rentContractDraftService): JsonResponse
    {
        $contract = $rentContractDraftService->findActive($rent_contract);

        return response()->json([
            'data' => new ContractResource($contract),
        ]);
    }

    public function approve(int $rent_contract_draft, RentContractDraftService $rentContractDraftService): JsonResponse
    {
        try {
            $contract = $rentContractDraftService->find($rent_contract_draft);
            $contract = $rentContractDraftService->approve($contract);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Rent contract approved successfully.',
            'data' => new ContractResource($contract),
        ]);
    }

    public function reject(
        RejectRentContractDraftRequest $request,
        int $rent_contract_draft,
        RentContractDraftService $rentContractDraftService,
    ): JsonResponse {
        try {
            $contract = $rentContractDraftService->find($rent_contract_draft);
            $contract = $rentContractDraftService->reject(
                $contract,
                $request->validated()['rejection_reason'] ?? null,
            );
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Rent contract rejected successfully.',
            'data' => new ContractResource($contract),
        ]);
    }

    public function downloadDocument(
        int $rent_contract_draft,
        RentContractDocumentService $rentContractDocumentService,
    ): Response {
        $contract = $rentContractDocumentService->findDraft($rent_contract_draft);

        return $rentContractDocumentService->downloadResponse($contract);
    }

    public function exportDocument(
        int $rent_contract_draft,
        RentContractDocumentService $rentContractDocumentService,
    ): Response {
        $contract = $rentContractDocumentService->findDraft($rent_contract_draft);

        return $rentContractDocumentService->exportResponse($contract);
    }

    public function sendDocumentEmail(
        SendRentContractDocumentRequest $request,
        int $rent_contract_draft,
        RentContractDocumentService $rentContractDocumentService,
    ): JsonResponse {
        try {
            $contract = $rentContractDocumentService->findDraft($rent_contract_draft);
            $rentContractDocumentService->sendEmail($contract, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Rent contract document sent successfully.',
        ]);
    }

    public function downloadActiveDocument(
        int $rent_contract,
        RentContractDocumentService $rentContractDocumentService,
    ): Response {
        $contract = $rentContractDocumentService->findActive($rent_contract);

        return $rentContractDocumentService->downloadResponse($contract);
    }

    public function exportActiveDocument(
        int $rent_contract,
        RentContractDocumentService $rentContractDocumentService,
    ): Response {
        $contract = $rentContractDocumentService->findActive($rent_contract);

        return $rentContractDocumentService->exportResponse($contract);
    }

    public function sendActiveDocumentEmail(
        SendRentContractDocumentRequest $request,
        int $rent_contract,
        RentContractDocumentService $rentContractDocumentService,
    ): JsonResponse {
        try {
            $contract = $rentContractDocumentService->findActive($rent_contract);
            $rentContractDocumentService->sendEmail($contract, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Rent contract document sent successfully.',
        ]);
    }
}
