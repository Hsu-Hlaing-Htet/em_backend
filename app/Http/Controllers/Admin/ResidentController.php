<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreResidentRequest;
use App\Http\Requests\Admin\UpdateResidentRequest;
use App\Http\Resources\Admin\ResidentResource;
use App\Models\User;
use App\Services\ResidentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResidentController extends Controller
{
    public function index(Request $request, ResidentService $residentService): JsonResponse
    {
        $paginator = $residentService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => ResidentResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreResidentRequest $request, ResidentService $residentService): JsonResponse
    {
        $resident = $residentService->create($request->validated());

        return response()->json([
            'message' => 'Resident created successfully.',
            'data' => new ResidentResource($resident),
        ], 201);
    }

    public function show(User $resident, ResidentService $residentService): JsonResponse
    {
        $resident = $residentService->find($resident->id);

        return response()->json([
            'data' => new ResidentResource($resident),
        ]);
    }

    public function update(UpdateResidentRequest $request, User $resident, ResidentService $residentService): JsonResponse
    {
        $resident = $residentService->find($resident->id);
        $resident = $residentService->update($resident, $request->validated());

        return response()->json([
            'message' => 'Resident updated successfully.',
            'data' => new ResidentResource($resident),
        ]);
    }

    public function destroy(User $resident, ResidentService $residentService): JsonResponse
    {
        $resident = $residentService->find($resident->id);
        $residentService->delete($resident);

        return response()->json([
            'message' => 'Resident deleted successfully.',
        ]);
    }
}
