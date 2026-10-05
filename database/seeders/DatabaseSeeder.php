<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            \App\Modules\Roles\Seeders\RolesAndPermissionsSeeder::class,
            CategorySeeder::class,
            \App\Modules\ReferenceData\Seeders\ElectoralHierarchySeeder::class,
        ]);

        // Test tenants and accounts are opt-in so a production seed can't create them by accident.
        if (filter_var(env('SEED_TEST_USERS', false), FILTER_VALIDATE_BOOLEAN)) {
            $this->call([TenancyTestSeeder::class, ElectionScheduleTestSeeder::class]);
        }
    }
}
