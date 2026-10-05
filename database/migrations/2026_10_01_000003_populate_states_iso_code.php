<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Populates states.iso_code (ISO 3166-2:NG), left NULL since the table was
 * created. State names are seeded lowercase from
 * states-and-lgas-and-wards-and-polling-units.json (see ElectoralHierarchySeeder).
 */
return new class extends Migration
{
    private const ISO_CODES = [
        'abia' => 'AB',
        'abuja' => 'FC', // Federal Capital Territory
        'adamawa' => 'AD',
        'akwa-ibom' => 'AK',
        'anambra' => 'AN',
        'bauchi' => 'BA',
        'bayelsa' => 'BY',
        'benue' => 'BE',
        'borno' => 'BO',
        'cross-river' => 'CR',
        'delta' => 'DE',
        'ebonyi' => 'EB',
        'edo' => 'ED',
        'ekiti' => 'EK',
        'enugu' => 'EN',
        'gombe' => 'GO',
        'imo' => 'IM',
        'jigawa' => 'JI',
        'kaduna' => 'KD',
        'kano' => 'KN',
        'katsina' => 'KT',
        'kebbi' => 'KE',
        'kogi' => 'KO',
        'kwara' => 'KW',
        'lagos' => 'LA',
        'nasarawa' => 'NA',
        'niger' => 'NI',
        'ogun' => 'OG',
        'ondo' => 'ON',
        'osun' => 'OS',
        'oyo' => 'OY',
        'plateau' => 'PL',
        'rivers' => 'RI',
        'sokoto' => 'SO',
        'taraba' => 'TA',
        'yobe' => 'YO',
        'zamfara' => 'ZA',
    ];

    public function up(): void
    {
        foreach (self::ISO_CODES as $name => $code) {
            DB::table('states')->where('name', $name)->update(['iso_code' => $code]);
        }
    }

    public function down(): void
    {
        DB::table('states')->whereIn('iso_code', array_values(self::ISO_CODES))->update(['iso_code' => null]);
    }
};
