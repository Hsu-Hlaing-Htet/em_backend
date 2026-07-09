<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUtilityItemRequest;
use App\Http\Requests\Admin\UpdateUtilityItemRequest;
use App\Http\Resources\Admin\UtilityItemResource;
use App\Models\Utility;
use App\Models\UtilityItem;
use App\Services\UtilityService;
use Illuminate\Http\JsonResponse;

class UtilityItemController extends Controller
{
    public function index(Utility $utility): JsonResponse
    {
        $utility->load('items.utilityType');

        return response()->json([
            'data' => UtilityItemResource::collection($utility->items)->resolve(),
        ]);
    }

    public function store(StoreUtilityItemRequest $request, Utility $utility, UtilityService $utilityService): JsonResponse
    {
        $item = $utilityService->createItem($utility, $request->validated());

        return response()->json([
            'message' => 'Utility item created successfully.',
            'data' => new UtilityItemResource($item),
        ], 201);
    }

    public function show(UtilityItem $utilityItem): JsonResponse
    {
        $utilityItem->load('utilityType');

        return response()->json([
            'data' => new UtilityItemResource($utilityItem),
        ]);
    }

    public function update(UpdateUtilityItemRequest $request, UtilityItem $utilityItem, UtilityService $utilityService): JsonResponse
    {
        $item = $utilityService->updateItem($utilityItem, $request->validated());

        return response()->json([
            'message' => 'Utility item updated successfully.',
            'data' => new UtilityItemResource($item),
        ]);
    }

    public function destroy(UtilityItem $utilityItem, UtilityService $utilityService): JsonResponse
    {
        $utilityService->deleteItem($utilityItem);

        return response()->json([
            'message' => 'Utility item deleted successfully.',
        ]);
    }
}
