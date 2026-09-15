<?php

namespace App\Modules\Observers\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Observers\Models\ObserverCheckIn;
use App\Modules\ReferenceData\Models\PollingUnit;
use Illuminate\Http\Request;

class CheckInController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'polling_unit_id' => ['required', 'integer', 'exists:polling_units,id'],
            'latitude'        => ['required', 'numeric', 'between:-90,90'],
            'longitude'       => ['required', 'numeric', 'between:-180,180'],
            'check_in_time'   => ['nullable', 'date'],
        ]);

        $pollingUnit = PollingUnit::findOrFail($data['polling_unit_id']);

        $distance = null;
        if ($pollingUnit->latitude && $pollingUnit->longitude) {
            $distance = $this->haversineMeters(
                (float) $data['latitude'],
                (float) $data['longitude'],
                (float) $pollingUnit->latitude,
                (float) $pollingUnit->longitude
            );
        }

        $checkIn = ObserverCheckIn::create([
            'user_id'          => $request->user()->id,
            'polling_unit_id'  => $pollingUnit->id,
            'check_in_time'    => $data['check_in_time'] ?? now(),
            'latitude'         => $data['latitude'],
            'longitude'        => $data['longitude'],
            'distance_from_pu' => $distance,
        ]);

        return response()->json([
            'message'  => 'Checked in successfully.',
            'check_in' => $checkIn,
        ], 201);
    }

    private function haversineMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371000;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return round($earthRadius * $c, 2);
    }
}
