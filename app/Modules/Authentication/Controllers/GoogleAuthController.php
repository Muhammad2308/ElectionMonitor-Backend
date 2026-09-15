<?php

namespace App\Modules\Authentication\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Authentication\Resources\UserResource;
use App\Modules\Authentication\Services\TokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoogleAuthController extends Controller
{
    public function __construct(protected TokenService $tokenService) {}

    /**
     * Sign in (or provision) a user from a Google Identity Services ID token.
     *
     * The frontend never talks to Google's OAuth token endpoint directly —
     * it only obtains a signed ID token via GIS and hands it to us here.
     * We verify that token server-side before trusting any of its claims.
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'credential'  => ['required', 'string'],
            'device_id'   => ['required', 'string', 'max:255'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $clientId = config('services.google.client_id');

        if (! $clientId) {
            return response()->json([
                'message' => 'Google sign-in is not configured on this server. Set GOOGLE_CLIENT_ID (backend) and VITE_GOOGLE_CLIENT_ID (frontend) to enable it.',
            ], 501);
        }

        // Verify the ID token with Google rather than trusting the client.
        // (tokeninfo is Google's officially supported endpoint for this;
        // a zero-round-trip alternative would verify the JWT signature
        // locally against Google's JWKS, at the cost of key-rotation
        // handling — fine to defer until this sees real traffic.)
        $verify = Http::get('https://oauth2.googleapis.com/tokeninfo', [
            'id_token' => $data['credential'],
        ]);

        if (! $verify->ok()) {
            throw ValidationException::withMessages([
                'credential' => ['Google could not verify this sign-in. Please try again.'],
            ]);
        }

        $payload = $verify->json();

        if (($payload['aud'] ?? null) !== $clientId) {
            throw ValidationException::withMessages([
                'credential' => ['This sign-in was issued for a different application.'],
            ]);
        }

        if (($payload['email_verified'] ?? 'false') !== 'true' || empty($payload['email'])) {
            throw ValidationException::withMessages([
                'credential' => ['Your Google account email must be verified to sign in.'],
            ]);
        }

        $email = $payload['email'];
        $isNewUser = ! User::where('email', $email)->exists();

        $user = User::firstOrCreate(
            ['email' => $email],
            [
                'name'     => $payload['name'] ?? explode('@', $email)[0],
                'password' => Hash::make(Str::random(40)),
                'status'   => 'active',
            ]
        );

        if ($user->isSuspended()) {
            throw ValidationException::withMessages([
                'email' => ['This account has been suspended. Contact your coordinator.'],
            ]);
        }

        if ($isNewUser) {
            $user->assignRole('observer');
        }

        $token = $this->tokenService->createToken(
            $user,
            $data['device_name'] ?? 'Observer Device',
            $data['device_id']
        );

        $user->update(['last_login_at' => now()]);

        return response()->json([
            'user'  => new UserResource($user->load('state')),
            'token' => $token,
        ]);
    }
}
