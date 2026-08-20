<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ChangePasswordRequest;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
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

    public function forgotPassword(ForgotPasswordRequest $request, AuthService $authService): JsonResponse
    {
        $authService->sendPasswordResetLink($request->validated('email'));

        return response()->json([
            'message' => 'If an account exists for that email, a password reset link has been sent.',
        ]);
    }

    public function resetPassword(ResetPasswordRequest $request, AuthService $authService): JsonResponse
    {
        $authService->resetPassword($request->validated());

        return response()->json([
            'message' => 'Your password has been reset successfully.',
        ]);
    }

    public function changePassword(ChangePasswordRequest $request, AuthService $authService): JsonResponse
    {
        $authService->changePassword(
            $request->user(),
            $request->validated('current_password'),
            $request->validated('password')
        );

        return response()->json([
            'message' => 'Your password has been changed successfully.',
        ]);
    }
}
