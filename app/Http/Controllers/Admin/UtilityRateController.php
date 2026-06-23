<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUtilityRateRequest;
use App\Http\Requests\Admin\UpdateUtilityRateRequest;
use App\Http\Resources\Admin\UtilityRateResource;
use App\Models\UtilityRate;
use App\Services\UtilityRateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UtilityRateController extends Controller
{
    public function index(Request $request, UtilityRateService $utilityRateService): JsonResponse
    {
        $this->authorize('viewAny', UtilityRate::class);

        $paginator = $utilityRateService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => UtilityRateResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreUtilityRateRequest $request, UtilityRateService $utilityRateService): JsonResponse
    {
        $this->authorize('create', UtilityRate::class);

        $utilityRate = $utilityRateService->create($request->validated());

        return response()->json([
            'message' => 'Utility rate created successfully.',
            'data' => new UtilityRateResource($utilityRate),
        ], 201);
    }

    public function show(UtilityRate $utilityRate): JsonResponse
    {
        $this->authorize('view', $utilityRate);

        $utilityRate->loadMissing('utilityType');

        return response()->json([
            'data' => new UtilityRateResource($utilityRate),
        ]);
    }

    public function update(
        UpdateUtilityRateRequest $request,
        UtilityRate $utilityRate,
        UtilityRateService $utilityRateService,
    ): JsonResponse {
        $this->authorize('update', $utilityRate);

        $utilityRate = $utilityRateService->update($utilityRate, $request->validated());

        return response()->json([
            'message' => 'Utility rate updated successfully.',
            'data' => new UtilityRateResource($utilityRate),
        ]);
    }

    public function destroy(UtilityRate $utilityRate, UtilityRateService $utilityRateService): JsonResponse
    {
        $this->authorize('delete', $utilityRate);

        $utilityRateService->delete($utilityRate);

        return response()->json([
            'message' => 'Utility rate deleted successfully.',
        ]);
    }
}
