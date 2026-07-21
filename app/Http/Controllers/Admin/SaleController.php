<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RejectSaleRequest;
use App\Http\Requests\Admin\StoreSaleRequest;
use App\Http\Requests\Admin\UpdateSaleRequest;
use App\Http\Resources\Admin\SaleResource;
use App\Services\SaleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

class SaleController extends Controller
{
    public function index(Request $request, SaleService $saleService): JsonResponse
    {
        $paginator = $saleService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => SaleResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreSaleRequest $request, SaleService $saleService): JsonResponse
    {
        try {
            $sale = $saleService->create($request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Sale submitted for approval successfully.',
            'data' => new SaleResource($sale),
        ], 201);
    }

    public function show(int $sale, SaleService $saleService): JsonResponse
    {
        $sale = $saleService->find($sale);

        return response()->json([
            'data' => new SaleResource($sale),
        ]);
    }

    public function update(
        UpdateSaleRequest $request,
        int $sale,
        SaleService $saleService,
    ): JsonResponse {
        try {
            $sale = $saleService->find($sale);
            $sale = $saleService->update($sale, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Sale updated successfully.',
            'data' => new SaleResource($sale),
        ]);
    }

    public function destroy(int $sale, SaleService $saleService): JsonResponse
    {
        try {
            $sale = $saleService->find($sale);
            $saleService->delete($sale);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Sale deleted successfully.',
        ]);
    }

    public function approve(int $sale, SaleService $saleService): JsonResponse
    {
        try {
            $sale = $saleService->find($sale);
            $sale = $saleService->approve($sale);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Sale approved successfully.',
            'data' => new SaleResource($sale),
        ]);
    }

    public function reject(RejectSaleRequest $request, int $sale, SaleService $saleService): JsonResponse
    {
        try {
            $sale = $saleService->find($sale);
            $sale = $saleService->reject($sale, $request->validated()['rejection_reason'] ?? null);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Sale rejected successfully.',
            'data' => new SaleResource($sale),
        ]);
    }

    public function activate(int $sale, SaleService $saleService): JsonResponse
    {
        try {
            $sale = $saleService->find($sale);
            $sale = $saleService->activate($sale);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Sale activated successfully.',
            'data' => new SaleResource($sale),
        ]);
    }

    public function deactivate(int $sale, SaleService $saleService): JsonResponse
    {
        try {
            $sale = $saleService->find($sale);
            $sale = $saleService->deactivate($sale);
        } catch (InvalidArgumentException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Sale deactivated successfully.',
            'data' => new SaleResource($sale),
        ]);
    }
}
