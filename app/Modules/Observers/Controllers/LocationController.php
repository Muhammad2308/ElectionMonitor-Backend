<?php

namespace App\Modules\Observers\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\Observers\Models\GpsLocation;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'latitude'      => ['required', 'numeric', 'between:-90,90'],
            'longitude'     => ['required', 'numeric', 'between:-180,180'],
            'battery_level' => ['nullable', 'integer', 'min:0', 'max:100'],
            'captured_at'   => ['nullable', 'date'],
        ]);

        $location = GpsLocation::create([
            'user_id'       => $request->user()->id,
            'latitude'      => $data['latitude'],
            'longitude'     => $data['longitude'],
            'battery_level' => $data['battery_level'] ?? null,
            'captured_at'   => $data['captured_at'] ?? now(),
        ]);

        return response()->json([
            'message'  => 'Location recorded.',
            'location' => $location,
        ], 201);
    }
}
