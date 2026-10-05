<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Sample upcoming elections for testing the observer election list. Idempotent on title.
 * Requires the elections, states and a cybernet superadmin to exist.
 */
class ElectionScheduleTestSeeder extends Seeder
{
    public function run(): void
    {
        $setBy = DB::table('users')->where('role_type', 'cybernet_superadmin')->value('id');
        if ($setBy === null) {
            $this->command->error('No cybernet superadmin found. Run RolesAndPermissionsSeeder first.');
            return;
        }

        $kano = DB::table('states')->where('name', 'kano')->value('id');
        $lagos = DB::table('states')->where('name', 'lagos')->value('id');
        $presidential = DB::table('elections')->where('type', 'presidential')->value('id');
        $governorship = DB::table('elections')->where('type', 'governorship')->value('id');

        $now = now();
        $schedules = [
            ['title' => 'Presidential Election 2027', 'election_id' => $presidential, 'state_id' => null,   'starts_at' => $now->copy()->addMonths(5)->setTime(8, 0)],
            ['title' => 'Kano Governorship Election 2027', 'election_id' => $governorship, 'state_id' => $kano,  'starts_at' => $now->copy()->addMonths(6)->setTime(8, 0)],
            ['title' => 'Lagos Governorship Election 2027', 'election_id' => $governorship, 'state_id' => $lagos, 'starts_at' => $now->copy()->addMonths(7)->setTime(8, 0)],
        ];

        foreach ($schedules as $row) {
            if ($row['election_id'] === null) {
                continue;
            }

            DB::table('election_schedules')->updateOrInsert(
                ['title' => $row['title']],
                [
                    'election_id' => $row['election_id'],
                    'state_id' => $row['state_id'],
                    'starts_at' => $row['starts_at'],
                    'ends_at' => $row['starts_at']->copy()->addHours(12),
                    'accreditation_starts_at' => $row['starts_at']->copy()->subMonths(1),
                    'status' => 'scheduled',
                    'set_by' => $setBy,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]
            );
        }

        $this->command->info('Sample upcoming elections seeded.');
    }
}
