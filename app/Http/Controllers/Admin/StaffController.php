<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffRequest;
use App\Http\Requests\Admin\UpdateStaffRequest;
use App\Http\Resources\Admin\StaffResource;
use App\Models\User;
use App\Services\StaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function index(Request $request, StaffService $staffService): JsonResponse
    {
        $paginator = $staffService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => StaffResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreStaffRequest $request, StaffService $staffService): JsonResponse
    {
        $staff = $staffService->create($request->validated());

        return response()->json([
            'message' => 'Staff created successfully.',
            'data' => new StaffResource($staff),
        ], 201);
    }

    public function show(User $staff, StaffService $staffService): JsonResponse
    {
        $staff = $staffService->find($staff->id);

        return response()->json([
            'data' => new StaffResource($staff),
        ]);
    }

    public function update(UpdateStaffRequest $request, User $staff, StaffService $staffService): JsonResponse
    {
        $staff = $staffService->find($staff->id);
        $staff = $staffService->update($staff, $request->validated());

        return response()->json([
            'message' => 'Staff updated successfully.',
            'data' => new StaffResource($staff),
        ]);
    }

    public function destroy(User $staff, StaffService $staffService): JsonResponse
    {
        $staff = $staffService->find($staff->id);
        $staffService->delete($staff);

        return response()->json([
            'message' => 'Staff deleted successfully.',
        ]);
    }
}
