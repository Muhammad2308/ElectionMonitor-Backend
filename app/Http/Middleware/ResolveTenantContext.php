<?php

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenantContext
{
    /**
     * Handle an incoming request.
     * Resolves the tenant_id from the authenticated user and sets the TenantContext.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user) {
            if ($user->role_type === 'cybernet_superadmin') {
                // Platform superadmins operate outside a single tenant context.
                // If they need to access a specific tenant's data, they must use runAsPlatform()
                // or we can allow them to pass a tenant header/query param if needed for specific platform endpoints.
                TenantContext::clear();
            } else if ($user->tenant_id) {
                // Standard tenant user (master admin, admin, observer)
                TenantContext::setTenantId($user->tenant_id);
            } else {
                // Failsafe: non-superadmin user without a tenant. Should never happen due to DB constraints.
                abort(403, 'User is not assigned to a tenant deployment.');
            }
        }

        return $next($request);
    }
}
