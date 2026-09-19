<?php

namespace App\Services\GeoLocationService;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeoLocationService
{
    public function lookup(string $ip): ?array
    {
        if ($ip === '127.0.0.1' || $ip === '::1' || $ip === 'localhost') {
            return null;
        }

        try {
            $response = Http::timeout(5)->get("http://ip-api.com/json/{$ip}");

            if ($response->successful() && $response->json('status') === 'success') {
                return [
                    'city' => $response->json('city'),
                    'region' => $response->json('regionName'),
                    'country' => $response->json('country'),
                ];
            }
        } catch (\Exception $e) {
            Log::warning("GeoLocation lookup failed for IP {$ip}: {$e->getMessage()}");
        }

        return null;
    }
}
