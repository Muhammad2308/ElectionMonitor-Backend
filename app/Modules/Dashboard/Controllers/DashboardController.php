<?php

namespace App\Modules\Dashboard\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Incidents\Models\Incident;
use App\Modules\Observers\Models\ObserverCheckIn;
use App\Modules\ReferenceData\Models\PollingUnit;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function metrics(Request $request)
    {
        $totalPollingUnits = PollingUnit::count();
        $coveredPollingUnits = PollingUnit::whereHas('checkIns')->count();

        $lastHour = Incident::where('created_at', '>=', now()->subHour())->count();

        return response()->json([
            'total_incidents'      => Incident::count(),
            'incidents_last_hour'  => $lastHour,
            'active_observers'     => User::role('observer')->whereHas('checkIns', function ($q) {
                $q->where('captured_at', '>=', now()->subHours(12));
            })->count(),
            'deployed_observers'   => User::role('observer')->count(),
            'open_incidents'       => Incident::open()->count(),
            'critical_incidents'   => Incident::critical()->count(),
            'polling_units'        => $totalPollingUnits,
            'covered_polling_units'=> $coveredPollingUnits,
            'coverage_percentage'  => $totalPollingUnits > 0
                ? round(($coveredPollingUnits / $totalPollingUnits) * 100, 1)
                : 0,
        ]);
    }

    public function incidents(Request $request)
    {
        $incidents = Incident::with(['category', 'pollingUnit'])
            ->latest('incident_time')
            ->limit($request->integer('limit', 10))
            ->get()
            ->map(fn ($incident) => [
                'id'          => $incident->id,
                'title'       => $incident->category?->name . ($incident->pollingUnit ? " — {$incident->pollingUnit->name}" : ''),
                'severity'    => $incident->severity,
                'status'      => $incident->status,
                'created_at'  => $incident->incident_time?->toIso8601String(),
            ]);

        return response()->json(['data' => $incidents]);
    }

    /**
     * Recent field activity (check-ins), standing in for the "reports" feed —
     * this schema doesn't have a separate submitted-report entity.
     */
    public function activity(Request $request)
    {
        $checkIns = ObserverCheckIn::with(['user', 'pollingUnit'])
            ->latest('captured_at')
            ->limit($request->integer('limit', 10))
            ->get()
            ->map(fn ($c) => [
                'id'         => $c->id,
                'title'      => ($c->user?->name ?? 'Observer') . " checked in at {$c->pollingUnit?->name}",
                'status'     => 'submitted',
                'created_at' => $c->captured_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $checkIns]);
    }

    public function activityChart(Request $request)
    {
        $hours = collect(range(0, 23))->map(function ($hour) {
            $start = now()->startOfDay()->addHours($hour);
            $end = (clone $start)->addHour();

            return [
                'hour'      => $start->format('H:00'),
                'incidents' => Incident::whereBetween('incident_time', [$start, $end])->count(),
                'checkins'  => ObserverCheckIn::whereBetween('captured_at', [$start, $end])->count(),
            ];
        });

        return response()->json(['data' => $hours]);
    }

    public function status(Request $request)
    {
        return response()->json([
            'status'          => 'live',
            'server_time'     => now()->toIso8601String(),
        ]);
    }
}
