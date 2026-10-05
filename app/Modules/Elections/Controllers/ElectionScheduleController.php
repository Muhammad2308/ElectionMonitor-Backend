<?php

namespace App\Modules\Elections\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ElectionScheduleController extends Controller
{
    /**
     * Upcoming elections relevant to the caller: national elections plus those in
     * the caller's state. Soonest first. Past and cancelled elections are excluded.
     */
    public function upcoming(Request $request)
    {
        $stateId = $request->user()->state_id;

        $rows = DB::table('election_schedules as s')
            ->join('elections as e', 'e.id', '=', 's.election_id')
            ->leftJoin('states as st', 'st.id', '=', 's.state_id')
            ->where('s.starts_at', '>=', now())
            ->whereIn('s.status', ['scheduled', 'active', 'postponed'])
            ->where(function ($q) use ($stateId) {
                $q->whereNull('s.state_id');
                if ($stateId !== null) {
                    $q->orWhere('s.state_id', $stateId);
                }
            })
            ->orderBy('s.starts_at')
            ->limit(20)
            ->get([
                's.id',
                's.title',
                's.starts_at',
                's.accreditation_starts_at',
                's.status',
                'e.name as election_name',
                'e.type as election_type',
                'e.scope',
                'st.name as state_name',
            ]);

        return response()->json(['data' => $rows]);
    }
}
