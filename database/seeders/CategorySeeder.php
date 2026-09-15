<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            ['name' => 'Violence', 'description' => 'Physical altercation or use of weapons'],
            ['name' => 'Vote Buying', 'description' => 'Offering money or incentives for votes'],
            ['name' => 'Ballot Snatching', 'description' => 'Forcible removal of ballot boxes or papers'],
            ['name' => 'Missing Materials', 'description' => 'Lack of essential electoral items'],
            ['name' => 'Intimidation', 'description' => 'Threatening behavior towards voters or officials'],
            ['name' => 'Delayed Opening', 'description' => 'Polling unit not open at official time'],
            ['name' => 'Result Manipulation', 'description' => 'Altering of vote counts or tallies'],
            ['name' => 'Other', 'description' => 'Any other incident not categorized above'],
        ];

        foreach ($categories as $category) {
            \DB::table('incident_categories')->insert($category);
        }
    }
}
