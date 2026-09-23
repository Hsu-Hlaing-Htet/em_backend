<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreMaintenanceCategoryRequest;
use App\Http\Requests\Admin\UpdateMaintenanceCategoryRequest;
use App\Http\Resources\Admin\MaintenanceCategoryResource;
use App\Models\MaintenanceCategory;
use App\Services\MaintenanceCategoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceCategoryController extends Controller
{
    public function index(Request $request, MaintenanceCategoryService $maintenanceCategoryService): JsonResponse
    {
        $this->authorize('viewAny', MaintenanceCategory::class);

        $paginator = $maintenanceCategoryService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => MaintenanceCategoryResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function options(MaintenanceCategoryService $maintenanceCategoryService): JsonResponse
    {
        $this->authorize('viewAny', MaintenanceCategory::class);

        return response()->json([
            'data' => MaintenanceCategoryResource::collection(
                $maintenanceCategoryService->options()
            )->resolve(),
        ]);
    }

    public function store(
        StoreMaintenanceCategoryRequest $request,
        MaintenanceCategoryService $maintenanceCategoryService,
    ): JsonResponse {
        $this->authorize('create', MaintenanceCategory::class);

        $category = $maintenanceCategoryService->create($request->validated());

        return response()->json([
            'message' => 'Maintenance category created successfully.',
            'data' => new MaintenanceCategoryResource($category),
        ], 201);
    }

    public function show(MaintenanceCategory $maintenanceCategory): JsonResponse
    {
        $this->authorize('view', $maintenanceCategory);

        return response()->json([
            'data' => new MaintenanceCategoryResource($maintenanceCategory),
        ]);
    }

    public function update(
        UpdateMaintenanceCategoryRequest $request,
        MaintenanceCategory $maintenanceCategory,
        MaintenanceCategoryService $maintenanceCategoryService,
    ): JsonResponse {
        $this->authorize('update', $maintenanceCategory);

        $category = $maintenanceCategoryService->update($maintenanceCategory, $request->validated());

        return response()->json([
            'message' => 'Maintenance category updated successfully.',
            'data' => new MaintenanceCategoryResource($category),
        ]);
    }

    public function destroy(
        MaintenanceCategory $maintenanceCategory,
        MaintenanceCategoryService $maintenanceCategoryService,
    ): JsonResponse {
        $this->authorize('delete', $maintenanceCategory);

        $maintenanceCategoryService->delete($maintenanceCategory);

        return response()->json([
            'message' => 'Maintenance category deleted successfully.',
        ]);
    }
}
