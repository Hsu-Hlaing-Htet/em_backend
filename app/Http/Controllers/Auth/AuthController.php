<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\Auth\AuthResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(LoginRequest $request, AuthService $authService): JsonResponse
    {
        $result = $authService->login(
            $request->validated('email'),
            $request->validated('password')
        );

        return response()->json([
            'message' => 'Login successful',
            'token' => $result['token'],
            'user' => new AuthResource($result['user']),
        ]);
    }

    public function logout(Request $request, AuthService $authService): JsonResponse
    {
        $authService->logout($request->user());

        return response()->json([
            'message' => 'Logout successful',
        ]);
    }

    public function me(Request $request, AuthService $authService): JsonResponse
    {
        return response()->json([
            'data' => new AuthResource($authService->currentUser($request->user())),
        ]);
    }
}
