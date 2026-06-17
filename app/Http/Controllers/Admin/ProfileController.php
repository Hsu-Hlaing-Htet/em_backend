<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProfileRequest;
use App\Http\Requests\Admin\UpdateProfileRequest;
use App\Http\Resources\Admin\ProfileResource;
use App\Models\Profile;
use App\Services\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function index(Request $request, ProfileService $profileService): JsonResponse
    {
        $paginator = $profileService->paginate($request->all());

        return response()->json([
            'data' => [
                'data' => ProfileResource::collection($paginator->items())->resolve(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function store(StoreProfileRequest $request, ProfileService $profileService): JsonResponse
    {
        $profile = $profileService->create($request->validated());

        return response()->json([
            'message' => 'Profile created successfully.',
            'data' => new ProfileResource($profile),
        ], 201);
    }

    public function show(Profile $profile): JsonResponse
    {
        $profile->load('user');

        return response()->json([
            'data' => new ProfileResource($profile),
        ]);
    }

    public function update(UpdateProfileRequest $request, Profile $profile, ProfileService $profileService): JsonResponse
    {
        $profile = $profileService->update($profile, $request->validated());

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data' => new ProfileResource($profile),
        ]);
    }

    public function destroy(Profile $profile, ProfileService $profileService): JsonResponse
    {
        $profileService->delete($profile);

        return response()->json([
            'message' => 'Profile deleted successfully.',
        ]);
    }
}
