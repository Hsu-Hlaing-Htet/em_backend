<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAccountRequest;
use App\Http\Requests\Admin\UpdateAccountRequest;
use App\Http\Resources\Admin\AccountResource;
use App\Models\Role;
use App\Models\User;
use App\Services\AccountService;
use App\Services\ProfileService;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ResidentController extends Controller
{
    /**
     * @return list<string>
     */
    private function allowedRoles(): array
    {
        return [Role::CUSTOMER];
    }

    public function index(Request $request, AccountService $accountService): JsonResponse
    {
        $paginator = $accountService->paginate($request->all(), $this->allowedRoles());

        return response()->json([
            'data' => [
                'data' => AccountResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(
        StoreAccountRequest $request,
        AccountService $accountService,
        UserService $userService,
        ProfileService $profileService,
    ): JsonResponse {
        $role = Role::findByName(Role::CUSTOMER);

        if (! $role) {
            abort(500, 'Customer role is not configured.');
        }

        $user = $accountService->create(
            $request->validated(),
            $role->id,
            $userService,
            $profileService,
        );

        return response()->json([
            'message' => 'Resident created successfully.',
            'data' => new AccountResource($user),
        ], 201);
    }

    public function show(User $user, AccountService $accountService): JsonResponse
    {
        $user = $accountService->find($user->id, $this->allowedRoles());

        return response()->json([
            'data' => new AccountResource($user),
        ]);
    }

    public function update(
        UpdateAccountRequest $request,
        User $user,
        AccountService $accountService,
        UserService $userService,
        ProfileService $profileService,
    ): JsonResponse {
        $accountService->find($user->id, $this->allowedRoles());

        $user = $accountService->update(
            $user,
            $request->validated(),
            $userService,
            $profileService,
        );

        return response()->json([
            'message' => 'Resident updated successfully.',
            'data' => new AccountResource($user),
        ]);
    }

    public function destroy(
        User $user,
        AccountService $accountService,
        UserService $userService,
    ): JsonResponse {
        $accountService->find($user->id, $this->allowedRoles());
        $accountService->delete($user, $userService);

        return response()->json([
            'message' => 'Resident deleted successfully.',
        ]);
    }
}
