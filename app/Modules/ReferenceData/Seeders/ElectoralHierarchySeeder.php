<?php

namespace App\Modules\ReferenceData\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ElectoralHierarchySeeder extends Seeder
{
    public function run(): void
    {
        $jsonPath = base_path('../states-and-lgas-and-wards-and-polling-units.json');
        
        if (!File::exists($jsonPath)) {
            $this->command->error("Master data JSON not found at: {$jsonPath}");
            return;
        }

        $data = json_decode(File::get($jsonPath), true);
        
        DB::table('polling_units')->delete();
        $this->command->info("Cleared existing polling units for fresh import.");

        DB::beginTransaction();
        try {
            foreach ($data as $stateData) {
                $stateName = strtolower($stateData['state']);
                DB::table('states')->updateOrInsert(
                    ['name' => $stateName],
                    ['created_at' => now(), 'updated_at' => now()]
                );
                
                $stateId = DB::table('states')->where('name', $stateName)->value('id');

                foreach ($stateData['lgas'] as $lgaData) {
                    $lgaName = strtolower($lgaData['lga']);
                    DB::table('lgas')->updateOrInsert(
                        ['state_id' => $stateId, 'name' => $lgaName],
                        ['created_at' => now(), 'updated_at' => now()]
                    );
                    
                    $lgaId = DB::table('lgas')->where('state_id', $stateId)->where('name', $lgaName)->value('id');

                    foreach ($lgaData['wards'] as $wardData) {
                        $wardName = strtolower($wardData['ward']);
                        DB::table('wards')->updateOrInsert(
                            ['lga_id' => $lgaId, 'name' => $wardName],
                            ['created_at' => now(), 'updated_at' => now()]
                        );
                        
                        $wardId = DB::table('wards')->where('lga_id', $lgaId)->where('name', $wardName)->value('id');

                        $puBuffer = [];
                        foreach ($wardData['polling_units'] as $index => $puName) {
                            $puBuffer[] = [
                                'ward_id' => $wardId,
                                'pu_code' => sprintf("S%02d-L%03d-W%04d-P%03d", $stateId, $lgaId, $wardId, $index + 1),
                                'name' => $puName,
                                'latitude' => null,
                                'longitude' => null,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];

                            if (count($puBuffer) >= 500) {
                                DB::table('polling_units')->insertOrIgnore($puBuffer);
                                $puBuffer = [];
                            }
                        }

                        if (!empty($puBuffer)) {
                            DB::table('polling_units')->insertOrIgnore($puBuffer);
                        }
                    }
                }
                $this->command->info("Completed State: " . $stateData['state']);
            }
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("Seeding failed at " . ($stateName ?? 'unknown') . ": " . $e->getMessage());
            throw $e;
        }
    }
}
