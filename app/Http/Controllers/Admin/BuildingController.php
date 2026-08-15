<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BulkDeleteBuildingsRequest;
use App\Http\Requests\Admin\StoreBuildingRequest;
use App\Http\Requests\Admin\UpdateBuildingRequest;
use App\Http\Resources\Admin\BuildingResource;
use App\Models\Building;
use App\Services\BuildingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuildingController extends Controller
{
    public function index(Request $request, BuildingService $buildingService): JsonResponse
    {
        $this->authorize('viewAny', Building::class);

        $paginator = $buildingService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => BuildingResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreBuildingRequest $request, BuildingService $buildingService): JsonResponse
    {
        $this->authorize('create', Building::class);

        $building = $buildingService->create($request->validated());

        return response()->json([
            'message' => 'Building created successfully.',
            'data' => new BuildingResource($building),
        ], 201);
    }

    public function show(Building $building): JsonResponse
    {
        $this->authorize('view', $building);

        return response()->json([
            'data' => new BuildingResource($building),
        ]);
    }

    public function update(UpdateBuildingRequest $request, Building $building, BuildingService $buildingService): JsonResponse
    {
        $this->authorize('update', $building);

        $building = $buildingService->update($building, $request->validated());

        return response()->json([
            'message' => 'Building updated successfully.',
            'data' => new BuildingResource($building),
        ]);
    }

    public function destroy(Building $building, BuildingService $buildingService): JsonResponse
    {
        $this->authorize('delete', $building);

        $hasRooms = $building->rooms()->exists();
        $buildingService->delete($building);

        return response()->json([
            'message' => $hasRooms
                ? 'Building archived successfully.'
                : 'Building deleted successfully.',
        ]);
    }

    public function bulkDestroy(BulkDeleteBuildingsRequest $request, BuildingService $buildingService): JsonResponse
    {
        $this->authorize('viewAny', Building::class);

        $deletedCount = $buildingService->bulkDelete($request->validated()['ids']);

        return response()->json([
            'message' => "{$deletedCount} buildings deleted successfully.",
            'deleted_count' => $deletedCount,
        ]);
    }

    public function archive(Building $building, BuildingService $buildingService): JsonResponse
    {
        $this->authorize('update', $building);

        return response()->json([
            'message' => 'Building archived successfully.',
            'data' => new BuildingResource($buildingService->archive($building)),
        ]);
    }

    public function activate(Building $building, BuildingService $buildingService): JsonResponse
    {
        $this->authorize('update', $building);

        return response()->json([
            'message' => 'Building reactivated successfully.',
            'data' => new BuildingResource($buildingService->activate($building)),
        ]);
    }
}
