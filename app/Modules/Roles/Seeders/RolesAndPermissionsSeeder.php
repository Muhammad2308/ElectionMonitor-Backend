<?php

namespace App\Modules\Roles\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use App\Models\User;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        // ── Permissions ───────────────────────────────────────────────────
        $permissions = [
            // Incidents
            'incidents.view',
            'incidents.create',
            'incidents.update',
            'incidents.delete',
            'incidents.view-all',

            // Observers
            'observers.view',
            'observers.manage',
            'observers.check-in',
            'observers.track-location',

            // Assignments
            'assignments.view',
            'assignments.view-own',
            'assignments.create',
            'assignments.bulk-create',
            'assignments.delete',

            // Users
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.suspend',
            'users.assign-role',

            // GIS
            'gis.view',
            'gis.view-observers',

            // Reports
            'reports.view',
            'reports.view-national',
            'reports.export',

            // Notifications
            'notifications.view',
            'notifications.send',

            // Audit
            'audit.view',

            // System
            'system.settings',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
        }

        // ── Roles & Permission Assignments ────────────────────────────────

        // Observer — field-level access only
        $observer = Role::firstOrCreate(['name' => 'observer', 'guard_name' => 'web']);
        $observer->syncPermissions([
            'incidents.view',
            'incidents.create',
            'assignments.view-own',
            'observers.check-in',
            'observers.track-location',
            'notifications.view',
        ]);

        // Ward Supervisor — ward-level oversight
        $wardSupervisor = Role::firstOrCreate(['name' => 'ward-supervisor', 'guard_name' => 'web']);
        $wardSupervisor->syncPermissions([
            'incidents.view',
            'incidents.create',
            'incidents.update',
            'assignments.view',
            'assignments.create',
            'observers.view',
            'observers.check-in',
            'observers.track-location',
            'gis.view',
            'notifications.view',
            'notifications.send',
        ]);

        // LGA Supervisor
        $lgaSupervisor = Role::firstOrCreate(['name' => 'lga-supervisor', 'guard_name' => 'web']);
        $lgaSupervisor->syncPermissions([
            'incidents.view',
            'incidents.create',
            'incidents.update',
            'assignments.view',
            'assignments.create',
            'assignments.bulk-create',
            'assignments.delete',
            'observers.view',
            'observers.manage',
            'observers.track-location',
            'gis.view',
            'gis.view-observers',
            'reports.view',
            'notifications.view',
            'notifications.send',
        ]);

        // State Coordinator
        $stateCoordinator = Role::firstOrCreate(['name' => 'state-coordinator', 'guard_name' => 'web']);
        $stateCoordinator->syncPermissions([
            'incidents.view',
            'incidents.view-all',
            'incidents.create',
            'incidents.update',
            'incidents.delete',
            'assignments.view',
            'assignments.create',
            'assignments.bulk-create',
            'assignments.delete',
            'observers.view',
            'observers.manage',
            'observers.track-location',
            'users.view',
            'users.create',
            'users.update',
            'users.suspend',
            'users.assign-role',
            'gis.view',
            'gis.view-observers',
            'reports.view',
            'reports.export',
            'notifications.view',
            'notifications.send',
        ]);

        // National Administrator
        $nationalAdmin = Role::firstOrCreate(['name' => 'national-admin', 'guard_name' => 'web']);
        $nationalAdmin->syncPermissions([
            'incidents.view',
            'incidents.view-all',
            'incidents.create',
            'incidents.update',
            'incidents.delete',
            'assignments.view',
            'assignments.create',
            'assignments.bulk-create',
            'assignments.delete',
            'observers.view',
            'observers.manage',
            'observers.track-location',
            'users.view',
            'users.create',
            'users.update',
            'users.suspend',
            'users.assign-role',
            'gis.view',
            'gis.view-observers',
            'reports.view',
            'reports.view-national',
            'reports.export',
            'notifications.view',
            'notifications.send',
            'audit.view',
        ]);

        // Super Administrator — all permissions
        $superAdmin = Role::firstOrCreate(['name' => 'super-admin', 'guard_name' => 'web']);
        $superAdmin->syncPermissions(Permission::all());

        // ── Seed default Super Admin user ─────────────────────────────────
        $admin = User::firstOrCreate(
            ['email' => 'admin@electwatch.com'],
            [
                'name'     => 'System Admin',
                'password' => bcrypt('EIP@Admin2026!'),
                'status'   => 'active',
            ]
        );
        $admin->assignRole($superAdmin);

        $this->command->info('Roles and permissions seeded successfully.');
        $this->command->info('Default super-admin: admin@electwatch.com / EIP@Admin2026!');
    }
}
