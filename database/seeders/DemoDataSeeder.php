<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Assignments\Models\ObserverAssignment;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Incidents\Models\IncidentCategory;
use App\Modules\Observers\Models\GpsLocation;
use App\Modules\Observers\Models\ObserverCheckIn;
use App\Modules\ReferenceData\Models\PollingUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoDataSeeder extends Seeder
{
    public function run(): void
    {
        $pollingUnits = PollingUnit::inRandomOrder()->limit(25)->get();

        if ($pollingUnits->isEmpty()) {
            $this->command->warn('No polling units found — run ElectoralHierarchySeeder first. Skipping demo data.');
            return;
        }

        // Give a handful of polling units real coordinates (Abuja-area jitter) so the map has pins.
        foreach ($pollingUnits as $pu) {
            $pu->update([
                'latitude'  => 9.05 + (mt_rand(-500, 500) / 10000),
                'longitude' => 7.49 + (mt_rand(-500, 500) / 10000),
            ]);
        }

        $categoryIds = IncidentCategory::pluck('id');
        $severities = ['low', 'medium', 'high', 'critical'];
        $statuses = ['open', 'investigating', 'resolved'];

        $observers = collect();
        foreach ($pollingUnits->take(15) as $i => $pu) {
            $observer = User::firstOrCreate(
                ['email' => "observer{$i}@electwatch.com"],
                [
                    'name'     => "Demo Observer {$i}",
                    'password' => bcrypt('Observer@2026!'),
                    'state_id' => $pu->ward?->lga?->state_id,
                    'status'   => 'active',
                ]
            );
            if (! $observer->hasRole('observer')) {
                $observer->assignRole('observer');
            }
            $observers->push($observer);

            ObserverAssignment::firstOrCreate([
                'user_id'         => $observer->id,
                'polling_unit_id' => $pu->id,
                'election_date'   => now()->toDateString(),
            ]);

            // Most observers have checked in; a couple are silent (no check-in).
            if ($i % 5 !== 0) {
                ObserverCheckIn::create([
                    'user_id'          => $observer->id,
                    'polling_unit_id'  => $pu->id,
                    'check_in_time'    => now()->subMinutes(rand(5, 240)),
                    'latitude'         => $pu->latitude,
                    'longitude'        => $pu->longitude,
                    'distance_from_pu' => rand(0, 40),
                ]);

                GpsLocation::create([
                    'user_id'      => $observer->id,
                    'latitude'     => $pu->latitude,
                    'longitude'    => $pu->longitude,
                    'battery_level'=> rand(20, 100),
                    'captured_at'  => now()->subMinutes(rand(1, 90)),
                ]);
            }
        }

        // A spread of incidents across the last 24 hours.
        foreach (range(1, 40) as $n) {
            $pu = $pollingUnits->random();
            $observer = $observers->random();

            $parties = ['APC', 'PDP', 'LP', 'NNPP', 'None'];

            Incident::create([
                'id'              => Str::uuid()->toString(),
                'user_id'         => $observer->id,
                'polling_unit_id' => $pu->id,
                'category_id'     => $categoryIds->random(),
                'severity'        => $severities[array_rand($severities)],
                'status'          => $statuses[array_rand($statuses)],
                'description'     => 'Demo incident report for local development and UI testing.',
                'involving_party' => $parties[array_rand($parties)],
                'incident_time'   => now()->subHours(rand(0, 23))->subMinutes(rand(0, 59)),
                'latitude'        => $pu->latitude,
                'longitude'       => $pu->longitude,
                'sync_status'     => 'synced',
            ]);
        }

        $this->command->info('Demo data seeded: 15 observers, assignments, check-ins, GPS pings, 40 incidents.');
    }
}
