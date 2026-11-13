<?php

namespace App\Services\CourierTracking\Drivers;

use App\Services\CourierTracking\CourierTrackingResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class BiteshipDriver implements CourierTrackingDriver
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl = 'https://api.biteship.com/v1',
        private readonly int $timeout = 15,
    ) {}

    public function supports(): array
    {
        return ['jnt', 'sicepat', 'shopee_express', 'jne', 'anteraja', 'ninja', 'id_express', 'pos', 'tiki', 'lion', 'sap', 'wahana'];
    }

    public function track(string $courier, string $trackingNumber): CourierTrackingResult
    {
        if (blank($this->apiKey)) {
            return CourierTrackingResult::failure('Biteship API key belum dikonfigurasi.', $courier, $trackingNumber);
        }

        $providerCourier = $this->mapCourier($courier);
        if ($providerCourier === null) {
            return CourierTrackingResult::failure("Kurir '{$courier}' tidak didukung oleh Biteship.", $courier, $trackingNumber);
        }

        try {
            $url = rtrim($this->baseUrl, '/') . '/trackings/' . rawurlencode($trackingNumber) . '/couriers/' . rawurlencode($providerCourier);
            $response = Http::timeout($this->timeout)
                ->acceptJson()
                ->withToken($this->apiKey)
                ->get($url);

            $json = $response->json();
            if (! $response->successful() || ! is_array($json)) {
                return CourierTrackingResult::failure($json['error'] ?? ($json['message'] ?? ('HTTP ' . $response->status())), $courier, $trackingNumber);
            }

            $data = $json;
            $history = $data['history'] ?? [];
            $events = [];
            foreach (is_array($history) ? $history : [] as $row) {
                if (! is_array($row)) continue;
                $desc = (string) ($row['note'] ?? $row['description'] ?? $row['status'] ?? '');
                if ($desc === '') continue;
                $events[] = [
                    'time' => isset($row['updated_at']) ? (string) $row['updated_at'] : (isset($row['date']) ? (string) $row['date'] : null),
                    'status' => $this->normalizeStatusText($desc),
                    'description' => $desc,
                    'location' => isset($row['service_type']) ? null : null,
                    'raw' => $row,
                ];
            }

            $summaryStatus = (string) ($data['status'] ?? '');
            $summaryDesc = '';
            $normalized = $this->normalizeStatus($summaryStatus, $summaryDesc, $events);

            return new CourierTrackingResult(
                true,
                $courier,
                $trackingNumber,
                $normalized,
                $summaryStatus !== '' ? $summaryStatus : null,
                $events,
                null,
                is_array($data) ? $data : [],
            );
        } catch (Throwable $e) {
            return CourierTrackingResult::failure($e->getMessage(), $courier, $trackingNumber);
        }
    }

    private function mapCourier(string $courier): ?string
    {
        return match ($courier) {
            'jnt' => 'jnt',
            'sicepat' => 'sicepat',
            'shopee_express' => 'spx',
            'jne' => 'jne',
            'anteraja' => 'anteraja',
            'ninja' => 'ninja',
            'id_express' => 'idexpress',
            'pos' => 'pos',
            'tiki' => 'tiki',
            'lion' => 'lionparcel',
            'sap' => 'sap',
            'wahana' => 'wahana',
            default => null,
        };
    }

    private function normalizeStatus(string $status, string $desc, array $events): ?string
    {
        $text = strtolower($status . ' ' . $desc . ' ' . implode(' ', array_map(fn ($e) => (string) ($e['description'] ?? ''), array_slice($events, 0, 5))));
        if (str_contains($text, 'delivered') || str_contains($text, 'terkirim') || str_contains($text, 'diterima')) return 'delivered';
        if (str_contains($text, 'return') || str_contains($text, 'retur') || str_contains($text, 'rts')) return 'returned';
        if (str_contains($text, 'gagal') || str_contains($text, 'failed') || str_contains($text, 'dispose')) return 'failed';
        if (str_contains($text, 'dropping_off') || str_contains($text, 'out for delivery') || str_contains($text, 'sedang diantar')) return 'out_for_delivery';
        if (str_contains($text, 'transit') || str_contains($text, 'on_the_way') || str_contains($text, 'perjalanan')) return 'in_transit';
        if (str_contains($text, 'picked') || str_contains($text, 'allocated') || str_contains($text, 'diambil')) return 'picked_up';
        if (str_contains($text, 'confirmed') || str_contains($text, 'placed') || str_contains($text, 'dikemas')) return 'packed';
        return $events !== [] || $status !== '' ? 'in_transit' : 'pending';
    }

    private function normalizeStatusText(string $desc): ?string
    {
        $text = strtolower($desc);
        if (str_contains($text, 'delivered') || str_contains($text, 'terkirim') || str_contains($text, 'diterima')) return 'delivered';
        if (str_contains($text, 'out for delivery') || str_contains($text, 'sedang diantar')) return 'out_for_delivery';
        if (str_contains($text, 'transit') || str_contains($text, 'perjalanan')) return 'in_transit';
        if (str_contains($text, 'picked') || str_contains($text, 'diambil')) return 'picked_up';
        if (str_contains($text, 'packed') || str_contains($text, 'dikemas')) return 'packed';
        return null;
    }
}