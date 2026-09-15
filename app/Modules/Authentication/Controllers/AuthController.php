<?php

namespace App\Modules\Authentication\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Authentication\Requests\LoginRequest;
use App\Modules\Authentication\Resources\UserResource;
use App\Modules\Authentication\Services\TokenService;

class AuthController extends Controller
{
    public function __construct(protected TokenService $tokenService) {}

    public function login(LoginRequest $request)
    {
        $user = $this->tokenService->validateCredentials(
            $request->email,
            $request->password
        );

        $token = $this->tokenService->createToken(
            $user,
            $request->device_name ?? 'Observer Device',
            $request->device_id
        );

        $user->update(['last_login_at' => now()]);

        return response()->json([
            'user'  => new UserResource($user->load('state')),
            'token' => $token,
        ]);
    }

    public function logout(\Illuminate\Http\Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logged out successfully.']);
    }
}