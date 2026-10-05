<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\UserCodeGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Two tenants in different states, each with a master admin, a state admin
 * and two observers, plus a paid licence so the tenants are active.
 * Test data only: emails use the reserved .test domain.
 *
 * Run after RolesAndPermissionsSeeder and ElectoralHierarchySeeder (states must exist).
 */
class TenancyTestSeeder extends Seeder
{
    private const TENANTS = [
        ['org' => 'Alpha Party', 'short' => 'ALP', 'state' => 'kano',   'state_code' => 'KN'],
        ['org' => 'Beta Party',  'short' => 'BTA', 'state' => 'lagos',  'state_code' => 'LA'],
    ];

    private const MEMBERS = [
        ['role' => 'state_master_admin', 'local' => 'master', 'count' => 1],
        ['role' => 'state_admin',        'local' => 'admin',  'count' => 1],
        ['role' => 'observer',           'local' => 'observer', 'count' => 2],
    ];

    public function run(): void
    {
        $password = env('SEED_TEST_PASSWORD') ?: Str::password(16);
        $now = now();
        $credentials = [];

        foreach (self::TENANTS as $spec) {
            $stateId = DB::table('states')->where('name', $spec['state'])->value('id');
            if ($stateId === null) {
                $this->command->error("State '{$spec['state']}' not found. Run ElectoralHierarchySeeder first.");
                return;
            }

            $orgId = DB::table('organisations')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'name' => $spec['org'],
                'short_code' => $spec['short'],
                'type' => 'neutral',
                'neutral_category' => 'other',
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $slug = Str::lower($spec['short']) . '-' . Str::lower($spec['state_code']);
            $tenantId = DB::table('tenants')->insertGetId([
                'uuid' => (string) Str::uuid(),
                'organisation_id' => $orgId,
                'scope' => 'state',
                'state_id' => $stateId,
                'name' => "{$spec['org']} · " . ucfirst($spec['state']),
                'slug' => $slug,
                'code' => "{$spec['short']}-{$spec['state_code']}",
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('tenant_states')->insert([
                'tenant_id' => $tenantId,
                'state_id' => $stateId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            DB::table('tenant_licences')->insert([
                'tenant_id' => $tenantId,
                'election_period' => 'Test election period',
                'fee_basis' => 'standard_state',
                'licence_fee' => 8000000,
                'currency' => 'NGN',
                'status' => 'paid',
                'paid_at' => $now,
                'valid_from' => $now->copy()->subDay(),
                'valid_to' => $now->copy()->addYear(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach (self::MEMBERS as $member) {
                for ($i = 1; $i <= $member['count']; $i++) {
                    $suffix = $member['count'] > 1 ? $i : '';
                    $email = "{$slug}.{$member['local']}{$suffix}@electwatch.test";

                    $userId = DB::table('users')->insertGetId([
                        'name' => ucfirst($member['local']) . " {$suffix}" . " ({$spec['short']})",
                        'email' => $email,
                        'password' => bcrypt($password),
                        'status' => 'active',
                        'role_type' => $member['role'],
                        'tenant_id' => $tenantId,
                        'organisation_id' => $orgId,
                        'state_id' => $stateId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);

                    $userCode = UserCodeGenerator::generate($tenantId, $spec['short'], $spec['state_code'], $member['role']);
                    DB::table('users')->where('id', $userId)->update(['user_code' => $userCode]);

                    $user = User::find($userId);
                    setPermissionsTeamId($tenantId);
                    $user->assignRole(Role::where('name', $member['role'])->where('guard_name', 'web')->firstOrFail());

                    $credentials[] = [$member['role'], $email, $userCode];
                }
            }
        }

        setPermissionsTeamId(0);

        $this->command->info('Test tenants and users seeded.');
        $this->command->table(['Role', 'Email', 'User code'], $credentials);
        $this->command->warn("Shared test password (shown once): {$password}");
    }
}
