<?php

namespace App\Http\Controllers\Api\User\WorldRegionController;

use App\Http\Controllers\Controller;
use App\Services\WorldRegionService\WorldRegionService;
use Illuminate\Http\Request;

class WorldRegionController extends Controller
{
    public function __construct(
        protected WorldRegionService $worldRegion
    ) {}

    public function countries()
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->worldRegion->getCountries(),
        ]);
    }

    public function states(Request $request)
    {
        $request->validate(['country' => 'required|string']);

        $states = $this->worldRegion->getStates($request->country);

        $data = collect($states)->map(fn ($s) => [
            'name' => $s['name'] ?? $s,
            'state_code' => $s['state_code'] ?? null,
        ]);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }

    public function cities(Request $request)
    {
        $request->validate([
            'country' => 'required|string',
            'state' => 'required|string',
        ]);

        $cities = $this->worldRegion->getCities($request->country, $request->state);

        $data = collect($cities)->map(fn ($c) => ['name' => $c]);

        return response()->json([
            'status' => 'success',
            'data' => $data,
        ]);
    }
}
