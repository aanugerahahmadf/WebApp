<?php

namespace App\Services\GeoNamesService;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class GeoNamesService
{
    protected string $baseUrl = 'http://api.geonames.org';

    public function __construct(
        protected string $username = ''
    ) {
        $this->username = config('services.geonames.username', '');
    }

    public function isConfigured(): bool
    {
        return ! empty($this->username);
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function getCountryGeoNameId(string $countryName): ?int
    {
        $key = 'gn_country_id_'.md5($countryName);

        return Cache::rememberForever($key, function () use ($countryName) {
            try {
                $res = Http::withOptions(['verify' => !app()->environment('production')])
                    ->timeout(10)
                    ->get("{$this->baseUrl}/searchJSON", [
                        'q' => $countryName,
                        'featureCode' => 'PCLI',
                        'maxRows' => 1,
                        'username' => $this->username,
                    ]);
                $data = $res->json();
                if (empty($data['geonames'])) {
                    return null;
                }

                return $data['geonames'][0]['geonameId'] ?? null;
            } catch (\Throwable $e) {
                Log::warning('[GeoNames] Failed to get country ID for '.$countryName.': '.$e->getMessage());

                return null;
            }
        });
    }

    public function getAdmin1(int $countryGeoNameId): array
    {
        return $this->fetchChildren($countryGeoNameId, 'ADM1');
    }

    public function getAdmin2(int $parentGeoNameId): array
    {
        return $this->fetchChildren($parentGeoNameId, 'ADM2');
    }

    public function getAdmin3(int $parentGeoNameId): array
    {
        return $this->fetchChildren($parentGeoNameId, 'ADM3');
    }

    public function getAdmin4(int $parentGeoNameId): array
    {
        return $this->fetchChildren($parentGeoNameId, 'ADM4');
    }

    public function getAdmin1ByName(string $countryName): array
    {
        $countryId = $this->getCountryGeoNameId($countryName);
        if (! $countryId) {
            return [];
        }

        return $this->getAdmin1($countryId);
    }

    public function getAdmin2ByStateName(string $countryName, string $stateName): array
    {
        $countryId = $this->getCountryGeoNameId($countryName);
        if (! $countryId) {
            return [];
        }

        $states = $this->getAdmin1($countryId);
        $stateId = null;
        foreach ($states as $s) {
            if (strcasecmp($s['name'], $stateName) === 0) {
                $stateId = $s['geonameId'];
                break;
            }
        }
        if (! $stateId) {
            return [];
        }

        return $this->getAdmin2($stateId);
    }

    public function getAdmin3ByDistrictName(string $countryName, string $stateName, string $districtName): array
    {
        $countryId = $this->getCountryGeoNameId($countryName);
        if (! $countryId) {
            return [];
        }

        $states = $this->getAdmin1($countryId);
        $stateId = null;
        foreach ($states as $s) {
            if (strcasecmp($s['name'], $stateName) === 0) {
                $stateId = $s['geonameId'];
                break;
            }
        }
        if (! $stateId) {
            return [];
        }

        $districts = $this->getAdmin2($stateId);
        $districtId = null;
        foreach ($districts as $d) {
            if (strcasecmp($d['name'], $districtName) === 0) {
                $districtId = $d['geonameId'];
                break;
            }
        }
        if (! $districtId) {
            return [];
        }

        return $this->getAdmin3($districtId);
    }

    public function searchPostalCodes(string $countryName, string $placeName, int $maxRows = 10): array
    {
        $key = 'gn_postal_'.md5($countryName.'|'.$placeName.'|'.$maxRows);

        return Cache::remember($key, 86400, function () use ($countryName, $placeName, $maxRows) {
            try {
                $res = Http::withOptions(['verify' => !app()->environment('production')])
                    ->timeout(10)
                    ->get("{$this->baseUrl}/postalCodeSearchJSON", [
                        'placename' => $placeName,
                        'country' => $countryName,
                        'maxRows' => $maxRows,
                        'username' => $this->username,
                    ]);
                $data = $res->json();
                if (empty($data['postalCodes'])) {
                    return [];
                }

                return collect($data['postalCodes'])->map(fn ($p) => [
                    'postal_code' => $p['postalCode'] ?? '',
                    'place_name' => $p['placeName'] ?? '',
                    'admin_name2' => $p['adminName2'] ?? '',
                    'admin_name3' => $p['adminName3'] ?? '',
                ])->toArray();
            } catch (\Throwable $e) {
                Log::warning('[GeoNames] Failed to search postal codes: '.$e->getMessage());

                return [];
            }
        });
    }

    protected function fetchChildren(int $geonameId, string $featureCode = ''): array
    {
        $key = 'gn_children_'.$geonameId.'_'.$featureCode;

        return Cache::remember($key, 604800, function () use ($geonameId, $featureCode) {
            try {
                $params = [
                    'geonameId' => $geonameId,
                    'username' => $this->username,
                ];
                if ($featureCode) {
                    $params['featureCode'] = $featureCode;
                }

                $res = Http::withOptions(['verify' => !app()->environment('production')])
                    ->timeout(10)
                    ->get("{$this->baseUrl}/childrenJSON", $params);
                $data = $res->json();
                if (empty($data['geonames'])) {
                    return [];
                }

                return collect($data['geonames'])->map(fn ($g) => [
                    'geonameId' => $g['geonameId'],
                    'name' => $g['name'],
                    'toponymName' => $g['toponymName'] ?? $g['name'],
                    'countryCode' => $g['countryCode'] ?? '',
                    'adminCode1' => $g['adminCode1'] ?? '',
                    'featureCode' => $g['fcode'] ?? '',
                ])->toArray();
            } catch (\Throwable $e) {
                Log::warning('[GeoNames] Failed to fetch children for '.$geonameId.': '.$e->getMessage());

                return [];
            }
        });
    }
}
