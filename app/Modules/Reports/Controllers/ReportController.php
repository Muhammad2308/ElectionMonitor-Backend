<?php

namespace App\Modules\Reports\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Modules\Incidents\Models\Incident;
use App\Modules\ReferenceData\Models\PollingUnit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function incidents(Request $request)
    {
        abort_unless($request->user()->can('reports.view'), 403);

        $byCategory = Incident::select('category_id', DB::raw('count(*) as total'))
            ->with('category:id,name')
            ->groupBy('category_id')
            ->get()
            ->map(fn ($row) => ['category' => $row->category?->name, 'total' => $row->total]);

        $bySeverity = Incident::select('severity', DB::raw('count(*) as total'))
            ->groupBy('severity')
            ->pluck('total', 'severity');

        $byStatus = Incident::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return response()->json([
            'by_category' => $byCategory,
            'by_severity' => $bySeverity,
            'by_status'   => $byStatus,
        ]);
    }

    public function observers(Request $request)
    {
        abort_unless($request->user()->can('reports.view'), 403);

        $totalObservers = User::role('observer')->count();
        $activeObservers = User::role('observer')->where('status', 'active')->count();
        $checkedIn = User::role('observer')->whereHas('checkIns')->count();
        $withIncidents = User::role('observer')->whereHas('incidents')->count();

        return response()->json([
            'total_observers'   => $totalObservers,
            'active_observers'  => $activeObservers,
            'checked_in'        => $checkedIn,
            'reporting_incidents' => $withIncidents,
        ]);
    }

    public function summary(Request $request)
    {
        abort_unless($request->user()->can('reports.view'), 403);

        $totalPollingUnits = PollingUnit::count();
        $coveredPollingUnits = PollingUnit::whereHas('checkIns')->count();

        return response()->json([
            'total_incidents'      => Incident::count(),
            'open_incidents'       => Incident::open()->count(),
            'critical_incidents'   => Incident::critical()->count(),
            'total_polling_units'  => $totalPollingUnits,
            'covered_polling_units'=> $coveredPollingUnits,
            'coverage_percentage'  => $totalPollingUnits > 0
                ? round(($coveredPollingUnits / $totalPollingUnits) * 100, 1)
                : 0,
            'total_observers'      => User::role('observer')->count(),
        ]);
    }
}
