<?php

namespace App\Modules\Observers\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Observers\Models\ObserverCheckIn;
use App\Modules\ReferenceData\Models\PollingUnit;
use App\Support\Geo;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CheckInController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'polling_unit_id' => ['required', 'integer', 'exists:polling_units,id'],
            'latitude'        => ['required', 'numeric', 'between:-90,90'],
            'longitude'       => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m'      => ['nullable', 'numeric', 'min:0'],
            // Request field kept as check_in_time to match the existing
            // field-app payload; it's written into the captured_at column.
            'check_in_time'   => ['nullable', 'date'],
        ]);

        $pollingUnit = PollingUnit::findOrFail($data['polling_unit_id']);

        $distance = null;
        if ($pollingUnit->latitude && $pollingUnit->longitude) {
            $distance = Geo::distanceMeters(
                (float) $data['latitude'],
                (float) $data['longitude'],
                (float) $pollingUnit->latitude,
                (float) $pollingUnit->longitude
            );
        }

        $checkIn = ObserverCheckIn::create([
            'uuid'             => (string) Str::uuid(),
            'observer_id'      => $request->user()->id,
            'polling_unit_id'  => $pollingUnit->id,
            'captured_at'      => $data['check_in_time'] ?? now(),
            'synced_at'        => now(),
            'latitude'         => $data['latitude'],
            'longitude'        => $data['longitude'],
            'accuracy_m'       => $data['accuracy_m'] ?? null,
            'distance_from_pu' => $distance,
        ]);

        return response()->json([
            'message'  => 'Checked in successfully.',
            'check_in' => $checkIn,
        ], 201);
    }
}
