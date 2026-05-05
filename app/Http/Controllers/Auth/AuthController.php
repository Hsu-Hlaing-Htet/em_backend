<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterOwnerRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function register(RegisterOwnerRequest $request): JsonResponse
    {
        $user = User::create([
            'name' => $request->string('name'),
            'email' => $request->string('email'),
            'phone' => $request->string('phone')->toString() ?: null,
            'address' => $request->string('address')->toString() ?: null,
            'role' => User::ROLE_OWNER,
            'password' => Hash::make($request->string('password')),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'message' => 'Registration successful.',
            'user' => $user,
            'redirect_to' => '/user/dashboard',
        ], 201);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $credentials = $request->safe()->only(['email', 'password']);

        if (! Auth::attempt($credentials, (bool) $request->boolean('remember'))) {
            return response()->json([
                'message' => 'Invalid credentials.',
            ], 422);
        }

        $request->session()->regenerate();

        /** @var User $user */
        $user = $request->user();

        return response()->json([
            'message' => 'Login successful.',
            'user' => $user,
            'redirect_to' => $this->redirectPath($user),
        ]);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'message' => 'Logged out successfully.',
        ]);
    }

    protected function redirectPath(User $user): string
    {
        return $user->role === User::ROLE_ADMIN
            ? '/admin/dashboard'
            : '/user/dashboard';
    }
}
