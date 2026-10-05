<?php

namespace App\Modules\Authentication\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Spatie teams: roles and permissions are scoped by tenant_id. Cybernet
 * platform users have no tenant and use team 0, matching how the seeder assigns them.
 */
class SetPermissionsTeam
{
    public function handle(Request $request, Closure $next): Response
    {
        setPermissionsTeamId($request->user()?->tenant_id ?? 0);

        return $next($request);
    }
}
