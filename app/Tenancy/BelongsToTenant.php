<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    /**
     * Boot the BelongsToTenant trait for a model.
     * Applies a global scope to filter queries by the current TenantContext.
     */
    protected static function bootBelongsToTenant(): void
    {
        // 1. Add global scope to automatically filter by tenant_id
        static::addGlobalScope('tenant_isolation', function (Builder $builder) {
            if (! TenantContext::isBypassed()) {
                $tenantId = TenantContext::getTenantId();

                if ($tenantId !== null) {
                    $builder->where($builder->getModel()->getTable() . '.tenant_id', $tenantId);
                } else {
                    // If no tenant context is set and we're not bypassed, 
                    // prevent access to tenant-owned tables entirely.
                    $builder->whereRaw('1 = 0');
                }
            }
        });

        // 2. Automatically set tenant_id when creating a new record
        static::creating(function (Model $model) {
            if (! TenantContext::isBypassed() && ! $model->getAttribute('tenant_id')) {
                $tenantId = TenantContext::getTenantId();

                if ($tenantId !== null) {
                    $model->setAttribute('tenant_id', $tenantId);
                }
            }
        });
    }

    /**
     * Define the relationship to the Tenant model.
     */
    public function tenant()
    {
        return $this->belongsTo(\App\Models\Tenant::class);
    }
}
