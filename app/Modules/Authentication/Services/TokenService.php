<?php

namespace App\Modules\Authentication\Services;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class TokenService
{
    public function createToken(User $user, string $deviceName, string $deviceId): string
    {
        $tokenName = "device:{$deviceId}";

        return $user->createToken($tokenName, ['*'], now()->addDays(30))->plainTextToken;
    }

    public function validateCredentials(string $email, string $password): User
    {
        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        if ($user->isSuspended()) {
            throw ValidationException::withMessages([
                'email' => ['This account has been suspended. Contact your coordinator.'],
            ]);
        }

        return $user;
    }
}