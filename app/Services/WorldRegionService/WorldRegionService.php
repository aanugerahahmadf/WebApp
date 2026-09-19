<?php

namespace App\Services\WorldRegionService;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WorldRegionService
{
    protected string $baseUrl = 'https://countriesnow.space/api/v0.1';

    public function getCountries(): array
    {
        return Cache::remember('world_countries', 86400, function () {
            try {
                $res = Http::withOptions(['verify' => !app()->environment('production')])
                    ->timeout(15)
                    ->get("{$this->baseUrl}/countries");
                $data = $res->json();
                if (isset($data['error']) && $data['error']) {
                    return [];
                }

                return collect($data['data'] ?? [])->pluck('country')->toArray();
            } catch (\Throwable $e) {
                Log::warning('[WorldRegion] Failed to fetch countries: '.$e->getMessage());

                return [];
            }
        });
    }

    public function getStates(string $country): array
    {
        $key = 'world_states_'.md5($country);

        if (Cache::has($key)) {
            return Cache::get($key);
        }

        try {
            $res = Http::withOptions(['verify' => !app()->environment('production')])
                ->timeout(15)
                ->post("{$this->baseUrl}/countries/states", [
                    'country' => $country,
                ]);
            $data = $res->json();
            if (isset($data['error']) && $data['error']) {
                Cache::put($key, [], 60);

                return [];
            }
            $states = $data['data']['states'] ?? [];
            Cache::put($key, $states, 86400);

            return $states;
        } catch (\Throwable $e) {
            Log::warning('[WorldRegion] Failed to fetch states for '.$country.': '.$e->getMessage());

            // Don't cache errors — retry on next request
            return [];
        }
    }

    public function getCities(string $country, string $state): array
    {
        $key = 'world_cities_'.md5($country.'|'.$state);

        if (Cache::has($key)) {
            return Cache::get($key);
        }

        try {
            $res = Http::withOptions(['verify' => !app()->environment('production')])
                ->timeout(15)
                ->post("{$this->baseUrl}/countries/state/cities", [
                    'country' => $country,
                    'state' => $state,
                ]);
            $data = $res->json();
            if (isset($data['error']) && $data['error']) {
                Cache::put($key, [], 60);

                return [];
            }
            $cities = $data['data'] ?? [];
            Cache::put($key, $cities, 86400);

            return $cities;
        } catch (\Throwable $e) {
            Log::warning('[WorldRegion] Failed to fetch cities for '.$country.'/'.$state.': '.$e->getMessage());

            return [];
        }
    }
}
