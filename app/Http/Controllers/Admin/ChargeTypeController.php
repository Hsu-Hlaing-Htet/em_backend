<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreChargeTypeRequest;
use App\Http\Requests\Admin\UpdateChargeTypeRequest;
use App\Http\Resources\Admin\ChargeTypeResource;
use App\Models\ChargeType;
use App\Services\ChargeTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChargeTypeController extends Controller
{
    public function index(Request $request, ChargeTypeService $chargeTypeService): JsonResponse
    {
        $this->authorize('viewAny', ChargeType::class);

        $paginator = $chargeTypeService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => ChargeTypeResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreChargeTypeRequest $request, ChargeTypeService $chargeTypeService): JsonResponse
    {
        $this->authorize('create', ChargeType::class);

        $chargeType = $chargeTypeService->create($request->validated());

        return response()->json([
            'message' => 'Charge type created successfully.',
            'data' => new ChargeTypeResource($chargeType),
        ], 201);
    }

    public function show(ChargeType $chargeType): JsonResponse
    {
        $this->authorize('view', $chargeType);

        return response()->json([
            'data' => new ChargeTypeResource($chargeType),
        ]);
    }

    public function update(
        UpdateChargeTypeRequest $request,
        ChargeType $chargeType,
        ChargeTypeService $chargeTypeService,
    ): JsonResponse {
        $this->authorize('update', $chargeType);

        $chargeType = $chargeTypeService->update($chargeType, $request->validated());

        return response()->json([
            'message' => 'Charge type updated successfully.',
            'data' => new ChargeTypeResource($chargeType),
        ]);
    }

    public function destroy(ChargeType $chargeType, ChargeTypeService $chargeTypeService): JsonResponse
    {
        $this->authorize('delete', $chargeType);

        $chargeTypeService->delete($chargeType);

        return response()->json([
            'message' => 'Charge type deleted successfully.',
        ]);
    }
}
