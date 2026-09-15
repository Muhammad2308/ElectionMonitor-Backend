<?php

namespace App\Modules\Authentication\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class DeviceBindingMiddleware
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $deviceId = $request->header('X-Device-ID');

        if (! $deviceId) {
            return response()->json([
                'message' => 'Missing device identification header.'
            ], 403);
        }

        // Validate that the token name matches the device ID
        $token = $request->user()?->currentAccessToken();
        
        if ($token && ! str_contains($token->name, $deviceId)) {
            return response()->json([
                'message' => 'Device mismatch. Unauthorized access from this hardware.'
            ], 403);
        }

        return $next($request);
    }
}
