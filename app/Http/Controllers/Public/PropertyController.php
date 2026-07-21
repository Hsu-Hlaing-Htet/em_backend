<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Resources\Public\PublicPropertyResource;
use App\Services\PublicPropertyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PropertyController extends Controller
{
    public function index(Request $request, PublicPropertyService $publicPropertyService): JsonResponse
    {
        $paginator = $publicPropertyService->paginate($request->all());

        return response()->json([
            'data' => PublicPropertyResource::collection($paginator->items())->resolve(),
            'meta' => [
                'total' => $paginator->total(),
                'per_page' => $paginator->perPage(),
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function featured(PublicPropertyService $publicPropertyService): JsonResponse
    {
        $properties = $publicPropertyService->featured();

        return response()->json([
            'data' => PublicPropertyResource::collection($properties)->resolve(),
        ]);
    }

    public function stats(PublicPropertyService $publicPropertyService): JsonResponse
    {
        return response()->json([
            'data' => $publicPropertyService->stats(),
        ]);
    }

    public function show(int $property, PublicPropertyService $publicPropertyService): JsonResponse
    {
        $room = $publicPropertyService->find($property);
        $room->load(['building', 'roomImages', 'contracts']);

        return response()->json([
            'data' => new PublicPropertyResource($room),
        ]);
    }
}
