<?php

namespace App\Http\Controllers\Api\User\GeoController;

use App\Http\Controllers\Controller;
use App\Services\GeoNamesService\GeoNamesService;
use App\Services\WorldRegionService\WorldRegionService;
use Illuminate\Http\Request;

class GeoController extends Controller
{
    public function __construct(
        protected GeoNamesService $geoNames,
        protected WorldRegionService $worldRegion
    ) {}

    public function admin2(Request $request)
    {
        $request->validate([
            'country' => 'required|string',
            'state' => 'required|string',
        ]);

        if (! $this->geoNames->isConfigured()) {
            return response()->json([
                'status' => 'error',
                'message' => 'GeoNames belum dikonfigurasi. Isi GEONAMES_USERNAME di .env',
                'data' => [],
            ]);
        }

        $districts = $this->geoNames->getAdmin2ByStateName(
            $request->country,
            $request->state
        );

        return response()->json([
            'status' => 'success',
            'data' => $districts,
        ]);
    }

    public function admin3(Request $request)
    {
        $request->validate([
            'country' => 'required|string',
            'state' => 'required|string',
            'district' => 'required|string',
        ]);

        if (! $this->geoNames->isConfigured()) {
            return response()->json([
                'status' => 'error',
                'message' => 'GeoNames belum dikonfigurasi. Isi GEONAMES_USERNAME di .env',
                'data' => [],
            ]);
        }

        $villages = $this->geoNames->getAdmin3ByDistrictName(
            $request->country,
            $request->state,
            $request->district
        );

        return response()->json([
            'status' => 'success',
            'data' => $villages,
        ]);
    }

    public function postalCodes(Request $request)
    {
        $request->validate([
            'country' => 'required|string',
            'city' => 'required|string',
        ]);

        if (! $this->geoNames->isConfigured()) {
            return response()->json([
                'status' => 'error',
                'message' => 'GeoNames belum dikonfigurasi. Isi GEONAMES_USERNAME di .env',
                'data' => [],
            ]);
        }

        $codes = $this->geoNames->searchPostalCodes(
            $request->country,
            $request->city
        );

        return response()->json([
            'status' => 'success',
            'data' => $codes,
        ]);
    }
}
