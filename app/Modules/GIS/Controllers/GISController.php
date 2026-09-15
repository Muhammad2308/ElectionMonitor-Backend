<?php

namespace App\Modules\GIS\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Incidents\Models\Incident;
use App\Modules\ReferenceData\Models\PollingUnit;
use Illuminate\Http\Request;

class GISController extends Controller
{
    /**
     * Polling units with coordinates, plus a live incident/coverage snapshot for the map.
     */
    public function pollingUnits(Request $request)
    {
        $query = PollingUnit::with('ward.lga.state')
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->withCount(['incidents' => fn ($q) => $q->open()])
            ->withCount('checkIns');

        if ($request->filled('state_id')) {
            $query->whereHas('ward.lga', fn ($q) => $q->where('state_id', $request->integer('state_id')));
        }

        $units = $query->get()->map(fn ($pu) => [
            'id'             => $pu->id,
            'pu_code'        => $pu->pu_code,
            'name'           => $pu->name,
            'latitude'       => $pu->latitude,
            'longitude'      => $pu->longitude,
            'ward'           => $pu->ward?->name,
            'lga'            => $pu->ward?->lga?->name,
            'state'          => $pu->ward?->lga?->state?->name,
            'open_incidents' => $pu->incidents_count,
            'has_coverage'   => $pu->check_ins_count > 0,
        ]);

        return response()->json(['data' => $units]);
    }

    /**
     * Observers' most recent known position, for the live map layer.
     */
    public function observers(Request $request)
    {
        $users = User::role(['observer', 'ward-supervisor', 'lga-supervisor'])
            ->with(['gpsLocations' => fn ($q) => $q->latest('captured_at')->limit(1)])
            ->with(['checkIns' => fn ($q) => $q->latest('check_in_time')->limit(1)->with('pollingUnit')])
            ->get()
            ->map(function (User $user) {
                $lastLocation = $user->gpsLocations->first();
                $lastCheckIn = $user->checkIns->first();

                return [
                    'id'             => $user->id,
                    'name'           => $user->name,
                    'status'         => $user->status,
                    'latitude'       => $lastLocation?->latitude ?? $lastCheckIn?->latitude,
                    'longitude'      => $lastLocation?->longitude ?? $lastCheckIn?->longitude,
                    'last_seen_at'   => $lastLocation?->captured_at?->toIso8601String() ?? $lastCheckIn?->check_in_time?->toIso8601String(),
                    'polling_unit'   => $lastCheckIn?->pollingUnit?->name,
                    'is_online'      => $lastLocation && $lastLocation->captured_at->gt(now()->subMinutes(30)),
                ];
            })
            ->filter(fn ($o) => $o['latitude'] && $o['longitude'])
            ->values();

        return response()->json(['data' => $users]);
    }

    /**
     * Recent open incidents as map pins.
     */
    public function incidents(Request $request)
    {
        $incidents = Incident::open()
            ->with(['category', 'pollingUnit'])
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->latest('incident_time')
            ->limit($request->integer('limit', 200))
            ->get()
            ->map(fn ($incident) => [
                'id'          => $incident->id,
                'latitude'    => $incident->latitude,
                'longitude'   => $incident->longitude,
                'severity'    => $incident->severity,
                'status'      => $incident->status,
                'category'    => $incident->category?->name,
                'polling_unit'=> $incident->pollingUnit?->name,
                'reported_at' => $incident->incident_time?->toIso8601String(),
            ]);

        return response()->json(['data' => $incidents]);
    }
}
