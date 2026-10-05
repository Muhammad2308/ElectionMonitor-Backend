<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * M12 — backfill_users_tenancy
 *
 * Maps existing users into the v3 tenancy model using their actual prior
 * Spatie role assignment (model_has_roles), not a blanket "lowest id becomes
 * superadmin, everyone else becomes observer" guess. Any user whose legacy
 * role can't be resolved aborts the migration for manual review — demoting a
 * real admin/coordinator to observer with no record of what they were is the
 * kind of mistake that shouldn't have a silent default.
 */
return new class extends Migration
{
    private const ROLE_MAP = [
        'super-admin'       => 'cybernet_superadmin',
        'national-admin'    => 'national_master_admin',
        'state-coordinator' => 'state_master_admin',
        'lga-supervisor'    => 'state_admin',
        'ward-supervisor'   => 'observer',
        'observer'          => 'observer',
    ];

    public function up(): void
    {
        $userCount = DB::table('users')->count();
        if ($userCount === 0) {
            return;
        }

        $legacyRoleByUserId = [];
        if (Schema::hasTable('model_has_roles') && Schema::hasTable('roles')) {
            $legacyRoleByUserId = DB::table('model_has_roles')
                ->join('roles', 'roles.id', '=', 'model_has_roles.role_id')
                ->where('model_has_roles.model_type', 'App\\Models\\User')
                ->pluck('roles.name', 'model_has_roles.model_id')
                ->toArray();
        }

        $users = DB::table('users')->orderBy('id')->get();

        $unresolved = [];
        foreach ($users as $user) {
            $legacyRole = $legacyRoleByUserId[$user->id] ?? null;
            if ($legacyRole === null || ! isset(self::ROLE_MAP[$legacyRole])) {
                $unresolved[] = $user->id;
            }
        }

        if ($unresolved !== []) {
            throw new \RuntimeException(
                'Cannot backfill users.role_type automatically: user id(s) ' .
                implode(', ', $unresolved) .
                ' have no recognised legacy Spatie role assignment. ' .
                'Assign a role (or set role_type manually) for these users before re-running this migration.'
            );
        }

        $orgId = DB::table('organisations')->insertGetId([
            'uuid' => Str::uuid(),
            'name' => 'Legacy Demo Organisation',
            'short_code' => 'LEG',
            'type' => 'neutral',
            'neutral_category' => 'other',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $stateId = DB::table('states')->first()?->id;

        $tenantId = DB::table('tenants')->insertGetId([
            'uuid' => Str::uuid(),
            'organisation_id' => $orgId,
            'scope' => 'state',
            'state_id' => $stateId,
            'name' => 'Legacy Demo Tenant',
            'slug' => 'legacy-demo-tenant',
            'code' => 'LEG-ST',
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // A tenant may only be 'active' when a paid licence covers today
        // (TenantLicenceService's own rule) — don't bypass that invariant
        // just because this tenant was created by a backfill script.
        DB::table('tenant_licences')->insert([
            'tenant_id' => $tenantId,
            'election_period' => 'Legacy backfill',
            'fee_basis' => 'standard_state',
            'licence_fee' => 0,
            'currency' => 'NGN',
            'status' => 'paid',
            'paid_at' => now(),
            'valid_from' => now()->subDay(),
            'valid_to' => now()->addYears(10),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sequence = 1;
        foreach ($users as $user) {
            $roleType = self::ROLE_MAP[$legacyRoleByUserId[$user->id]];

            if ($roleType === 'cybernet_superadmin') {
                DB::table('users')->where('id', $user->id)->update([
                    'role_type' => $roleType,
                    // organisation_id and tenant_id remain NULL
                ]);
                continue;
            }

            DB::table('users')->where('id', $user->id)->update([
                'organisation_id' => $orgId,
                'tenant_id' => $tenantId,
                'role_type' => $roleType,
                'user_code' => 'LEG-ST-' . strtoupper(substr($roleType, 0, 2)) . '-' . str_pad($sequence, 5, '0', STR_PAD_LEFT),
            ]);
            $sequence++;
        }
    }

    public function down(): void
    {
        DB::table('users')->update([
            'organisation_id' => null,
            'tenant_id' => null,
            'user_code' => null,
            'role_type' => null,
        ]);

        DB::table('tenant_licences')->where('election_period', 'Legacy backfill')->delete();
        DB::table('tenants')->where('slug', 'legacy-demo-tenant')->delete();
        DB::table('organisations')->where('short_code', 'LEG')->delete();
    }
};
