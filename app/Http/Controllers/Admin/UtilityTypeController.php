<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUtilityTypeRequest;
use App\Http\Requests\Admin\UpdateUtilityTypeRequest;
use App\Http\Resources\Admin\UtilityTypeResource;
use App\Models\UtilityType;
use App\Services\UtilityTypeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UtilityTypeController extends Controller
{
    public function index(Request $request, UtilityTypeService $utilityTypeService): JsonResponse
    {
        $this->authorize('viewAny', UtilityType::class);

        $paginator = $utilityTypeService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => UtilityTypeResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreUtilityTypeRequest $request, UtilityTypeService $utilityTypeService): JsonResponse
    {
        $this->authorize('create', UtilityType::class);

        $utilityType = $utilityTypeService->create($request->validated());

        return response()->json([
            'message' => 'Utility type created successfully.',
            'data' => new UtilityTypeResource($utilityType),
        ], 201);
    }

    public function show(UtilityType $utilityType): JsonResponse
    {
        $this->authorize('view', $utilityType);

        return response()->json([
            'data' => new UtilityTypeResource($utilityType),
        ]);
    }

    public function update(
        UpdateUtilityTypeRequest $request,
        UtilityType $utilityType,
        UtilityTypeService $utilityTypeService,
    ): JsonResponse {
        $this->authorize('update', $utilityType);

        $utilityType = $utilityTypeService->update($utilityType, $request->validated());

        return response()->json([
            'message' => 'Utility type updated successfully.',
            'data' => new UtilityTypeResource($utilityType),
        ]);
    }

    public function destroy(UtilityType $utilityType, UtilityTypeService $utilityTypeService): JsonResponse
    {
        $this->authorize('delete', $utilityType);

        $utilityTypeService->delete($utilityType);

        return response()->json([
            'message' => 'Utility type deleted successfully.',
        ]);
    }
}
