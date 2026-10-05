<?php

namespace App\Modules\Roles\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;
use Illuminate\Support\Str;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Permissions ───────────────────────────────────────────────────
        $permissions = [
            // Platform (Cybernet only)
            'platform.manage_orgs',
            'platform.manage_tenants',
            'platform.manage_schedules',
            'platform.purge_data',

            // Incidents
            'incidents.view',
            'incidents.view-all',
            'incidents.create',
            'incidents.update',
            'incidents.delete',

            // Observers
            'observers.view',
            'observers.manage',
            'observers.check-in',

            // Assignments & Logistics
            'assignments.view',
            'assignments.manage',
            'operational_schedules.manage',

            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.suspend',
            'users.delete',
            'users.assign-role',

            // Polling unit location capture & review
            'polling-units.submit',
            'polling-units.review',

            // GIS
            'gis.view',

            // Reports
            'reports.view',
            'reports.export',

            // Notifications
            'notifications.view',
            'notifications.send',
            
            // Support Access
            'support_access.grant',
            
            // Audit
            'audit.view',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // Set global team context so roles are created globally (tenant_id = null)
        setPermissionsTeamId(null);

        // ── Roles & Permission Assignments ────────────────────────────────

        // 1. Observer
        $observer = Role::firstOrCreate(['name' => 'observer', 'guard_name' => 'web']);
        $observer->syncPermissions([
            // Access matrix: observers read "own reports" — incidents.view
            // without incidents.view-all scopes IncidentController::index()
            // to the authenticated user's own reports.
            'incidents.view',
            'incidents.create',
            'observers.check-in',
            'polling-units.submit',
            'notifications.view',
        ]);

        // 2. State Admin
        $stateAdmin = Role::firstOrCreate(['name' => 'state_admin', 'guard_name' => 'web']);
        $stateAdmin->syncPermissions([
            'incidents.view',
            'incidents.view-all',
            'incidents.create',
            'incidents.update',
            'assignments.view',
            'assignments.manage',
            'observers.view',
            'gis.view',
            'notifications.view',
            'reports.view',
            'polling-units.review',
        ]);

        // 3. State Master Admin
        $stateMasterAdmin = Role::firstOrCreate(['name' => 'state_master_admin', 'guard_name' => 'web']);
        $stateMasterAdmin->syncPermissions([
            'incidents.view',
            'incidents.view-all',
            'incidents.create',
            'incidents.update',
            'incidents.delete',
            'assignments.view',
            'assignments.manage',
            'operational_schedules.manage',
            'observers.view',
            'observers.manage',
            'users.view',
            'users.create',
            'users.update',
            'users.suspend',
            'users.assign-role',
            'gis.view',
            'reports.view',
            'reports.export',
            'notifications.view',
            'notifications.send',
            'support_access.grant',
            'audit.view',
            'polling-units.review',
        ]);

        // 4. National Master Admin
        $nationalMasterAdmin = Role::firstOrCreate(['name' => 'national_master_admin', 'guard_name' => 'web']);
        $nationalMasterAdmin->syncPermissions([
            // Inherits all state_master_admin permissions plus cross-state views
            'incidents.view',
            'incidents.view-all',
            'incidents.create',
            'incidents.update',
            'incidents.delete',
            'assignments.view',
            'assignments.manage',
            'operational_schedules.manage',
            'observers.view',
            'observers.manage',
            'users.view',
            'users.create',
            'users.update',
            'users.suspend',
            'users.assign-role',
            'gis.view',
            'reports.view',
            'reports.export',
            'notifications.view',
            'notifications.send',
            'support_access.grant',
            'audit.view',
            'polling-units.review',
        ]);

        // 5. Cybernet Superadmin
        $cybernetSuperadmin = Role::firstOrCreate(['name' => 'cybernet_superadmin', 'guard_name' => 'web']);
        $cybernetSuperadmin->syncPermissions([
            'platform.manage_orgs',
            'platform.manage_tenants',
            'platform.manage_schedules',
            'platform.purge_data',
            'audit.view', // Can view platform audit logs
            // Cannot view tenant data (incidents, assignments) without a grant
        ]);

        // ── Seed default Cybernet Superadmin user ──────────────────────────
        $password = env('SEED_ADMIN_PASSWORD') ?: Str::password(20);
        $admin = User::firstOrCreate(
            ['email' => 'admin@electwatch.com'],
            [
                'name'      => 'Cybernet System Admin',
                'password'  => $password,
                'status'    => 'active',
                'role_type' => 'cybernet_superadmin',
                // organisation_id and tenant_id remain NULL
            ]
        );
        // Set team id to 0 for the global assignment (since model_has_roles.tenant_id cannot be null)
        setPermissionsTeamId(0);
        $admin->assignRole($cybernetSuperadmin);

        $this->command->info('Roles and permissions seeded.');
        if ($admin->wasRecentlyCreated) {
            $this->command->warn("Cybernet superadmin created: admin@electwatch.com / {$password}");
            $this->command->warn('Shown once. Change it after first login.');
        }
    }
}
