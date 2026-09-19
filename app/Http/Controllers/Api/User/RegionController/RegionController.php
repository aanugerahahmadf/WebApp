<?php

namespace App\Http\Controllers\Api\User\RegionController;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Laravolt\Indonesia\Models\City;
use Laravolt\Indonesia\Models\District;
use Laravolt\Indonesia\Models\Province;
use Laravolt\Indonesia\Models\Village;

class RegionController extends Controller
{
    public function provinces(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => Province::select('id', 'code', 'name')->orderBy('name')->get(),
        ]);
    }

    public function cities(string $provinceCode): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => City::select('id', 'code', 'name', 'province_code')
                ->where('province_code', $provinceCode)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function districts(string $cityCode): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => District::select('id', 'code', 'name', 'city_code')
                ->where('city_code', $cityCode)
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function villages(string $districtCode): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => Village::select('id', 'code', 'name', 'district_code')
                ->where('district_code', $districtCode)
                ->orderBy('name')
                ->get(),
        ]);
    }
}
