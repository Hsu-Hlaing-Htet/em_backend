<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRoleRequest;
use App\Http\Requests\Admin\UpdateRoleRequest;
use App\Http\Resources\Admin\RoleResource;
use App\Models\Role;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RoleController extends Controller
{
    public function index(Request $request, RoleService $roleService): JsonResponse
    {
        $paginator = $roleService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => RoleResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreRoleRequest $request, RoleService $roleService): JsonResponse
    {
        $role = $roleService->create($request->validated());

        return response()->json([
            'message' => 'Role created successfully.',
            'data' => new RoleResource($role),
        ], 201);
    }

    public function show(Role $role): JsonResponse
    {
        return response()->json([
            'data' => new RoleResource($role),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role, RoleService $roleService): JsonResponse
    {
        $role = $roleService->update($role, $request->validated());

        return response()->json([
            'message' => 'Role updated successfully.',
            'data' => new RoleResource($role),
        ]);
    }

    public function destroy(Role $role, RoleService $roleService): JsonResponse
    {
        $roleService->delete($role);

        return response()->json([
            'message' => 'Role deleted successfully.',
        ]);
    }
}
