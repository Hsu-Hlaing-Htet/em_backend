<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreContractRequest;
use App\Http\Requests\Admin\UpdateContractRequest;
use App\Http\Resources\Admin\ContractResource;
use App\Models\Contract;
use App\Services\ContractService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class ContractController extends Controller
{
    public function index(Request $request, ContractService $contractService): JsonResponse
    {
        $paginator = $contractService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => ContractResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreContractRequest $request, ContractService $contractService): JsonResponse
    {
        try {
            $contract = $contractService->create($request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Contract created successfully.',
            'data' => new ContractResource($contract),
        ], 201);
    }

    public function show(Contract $contract): JsonResponse
    {
        $contract->load(['user', 'room.building', 'paymentPlan', 'creator', 'approver']);

        return response()->json([
            'data' => new ContractResource($contract),
        ]);
    }

    public function update(UpdateContractRequest $request, Contract $contract, ContractService $contractService): JsonResponse
    {
        try {
            $contract = $contractService->update($contract, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Contract updated successfully.',
            'data' => new ContractResource($contract),
        ]);
    }

    public function destroy(Contract $contract, ContractService $contractService): JsonResponse
    {
        $contractService->delete($contract);

        return response()->json([
            'message' => 'Contract deleted successfully.',
        ]);
    }

    public function submit(Contract $contract, ContractService $contractService): JsonResponse
    {
        try {
            $contract = $contractService->submit($contract);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Contract submitted successfully.',
            'data' => new ContractResource($contract),
        ]);
    }

    public function approve(Contract $contract, ContractService $contractService): JsonResponse
    {
        try {
            $contract = $contractService->approve($contract);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Contract approved successfully.',
            'data' => new ContractResource($contract),
        ]);
    }

    public function reject(Contract $contract, ContractService $contractService): JsonResponse
    {
        try {
            $contract = $contractService->reject($contract);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Contract rejected successfully.',
            'data' => new ContractResource($contract),
        ]);
    }
}
