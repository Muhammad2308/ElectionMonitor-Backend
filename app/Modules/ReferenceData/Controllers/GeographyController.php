<?php

namespace App\Modules\ReferenceData\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\ReferenceData\Models\State;
use App\Modules\ReferenceData\Models\Lga;
use App\Modules\ReferenceData\Models\Ward;
use App\Modules\ReferenceData\Models\PollingUnit;
use App\Modules\ReferenceData\Resources\PollingUnitResource;
use Illuminate\Http\Request;

class GeographyController extends Controller
{
    public function states()
    {
        return response()->json(State::all());
    }

    public function lgas(Request $request)
    {
        $user = $request->user();
        $query = Lga::query();

        if ($user && $user->state_id) {
            $query->where('state_id', $user->state_id);
        }

        return response()->json($query->get());
    }

    public function wards(Request $request)
    {
        $user = $request->user();
        $query = Ward::query();

        if ($user && $user->state_id) {
            $query->whereHas('lga', function($q) use ($user) {
                $q->where('state_id', $user->state_id);
            });
        }

        return response()->json($query->get());
    }

    public function pollingUnits(Request $request)
    {
        $user = $request->user();
        // Use a high-performance query for pre-fetching
        $query = PollingUnit::query();

        if ($user && $user->state_id) {
            $query->whereHas('ward.lga', function($q) use ($user) {
                $q->where('state_id', $user->state_id);
            });
        }

        // Return a collection wrapped in a resource for consistent schema
        return PollingUnitResource::collection($query->get());
    }
}
