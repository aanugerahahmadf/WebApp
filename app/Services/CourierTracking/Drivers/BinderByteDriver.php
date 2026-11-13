<?php

namespace App\Services\CourierTracking\Drivers;

use App\Services\CourierTracking\CourierTrackingResult;
use Illuminate\Support\Facades\Http;
use Throwable;

class BinderByteDriver implements CourierTrackingDriver
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $baseUrl = 'https://api.binderbyte.com/v1/track',
        private readonly int $timeout = 15,
    ) {}

    public function supports(): array
    {
        return ['jnt', 'jnt_cargo', 'sicepat', 'shopee_express', 'jne', 'anteraja', 'ninja', 'id_express', 'pos', 'tiki', 'lion', 'sap', 'wahana'];
    }

    public function track(string $courier, string $trackingNumber): CourierTrackingResult
    {
        if (blank($this->apiKey)) {
            return CourierTrackingResult::failure('BinderByte API key belum dikonfigurasi.', $courier, $trackingNumber);
        }

        $providerCourier = $this->mapCourier($courier);
        if ($providerCourier === null) {
            return CourierTrackingResult::failure("Kurir '{$courier}' tidak didukung oleh BinderByte.", $courier, $trackingNumber);
        }

        try {
            $response = Http::timeout($this->timeout)->acceptJson()->get($this->baseUrl, [
                'api_key' => $this->apiKey,
                'courier' => $providerCourier,
                'awb' => $trackingNumber,
            ]);

            $json = $response->json();
            if (! $response->successful() || ! is_array($json)) {
                return CourierTrackingResult::failure(
                    $json['message'] ?? ('HTTP ' . $response->status()),
                    $courier,
                    $trackingNumber
                );
            }

            $apiStatus = $json['status'] ?? $response->status();
            if ((int) $apiStatus !== 200) {
                return CourierTrackingResult::failure(
                    $json['message'] ?? 'Gagal mengambil data pelacakan.',
                    $courier,
                    $trackingNumber
                );
            }

            $data = $json['data'] ?? [];
            $summary = $data['summary'] ?? [];
            $history = $data['history'] ?? [];
            $events = [];
            foreach (is_array($history) ? $history : [] as $row) {
                if (! is_array($row)) continue;
                $desc = (string) ($row['desc'] ?? $row['description'] ?? '');
                if ($desc === '') continue;
                $events[] = [
                    'time' => isset($row['date']) ? (string) $row['date'] : null,
                    'status' => $this->normalizeStatusText($desc),
                    'description' => $desc,
                    'location' => isset($row['location']) ? (string) $row['location'] : null,
                    'raw' => $row,
                ];
            }

            $summaryStatus = (string) ($summary['status'] ?? '');
            $summaryDesc = (string) ($summary['desc'] ?? $summary['description'] ?? '');
            $normalized = $this->normalizeStatus($summaryStatus, $summaryDesc, $events);

            return new CourierTrackingResult(
                true,
                $courier,
                $trackingNumber,
                $normalized,
                $summaryDesc !== '' ? $summaryDesc : ($summaryStatus !== '' ? $summaryStatus : null),
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
            'jnt_cargo' => 'jnt_cargo',
            'sicepat' => 'sicepat',
            'shopee_express' => 'spx',
            'jne' => 'jne',
            'anteraja' => 'anteraja',
            'ninja' => 'ninja',
            'id_express' => 'idexpress',
            'pos' => 'pos',
            'tiki' => 'tiki',
            'lion' => 'lion',
            'sap' => 'sap',
            'wahana' => 'wahana',
            default => null,
        };
    }

    private function normalizeStatus(string $status, string $desc, array $events): ?string
    {
        $text = strtolower($status . ' ' . $desc . ' ' . implode(' ', array_map(fn ($e) => (string) ($e['description'] ?? ''), array_slice($events, 0, 5))));
        if (str_contains($text, 'delivered') || str_contains($text, 'terkirim') || str_contains($text, 'diterima') || str_contains($text, 'received') || str_contains($text, 'selesai')) {
            return 'delivered';
        }
        if (str_contains($text, 'return') || str_contains($text, 'retur') || str_contains($text, 'kembali') || str_contains($text, 'rts')) {
            return 'returned';
        }
        if (str_contains($text, 'gagal') || str_contains($text, 'failed') || str_contains($text, 'cancel')) {
            return 'failed';
        }
        if (str_contains($text, 'sedang diantar') || str_contains($text, 'out for delivery') || str_contains($text, 'dengan kurir') || str_contains($text, 'antar ke alamat') || str_contains($text, 'on delivery')) {
            return 'out_for_delivery';
        }
        if (str_contains($text, 'transit') || str_contains($text, 'perjalanan') || str_contains($text, 'dalam proses') || str_contains($text, 'menuju') || str_contains($text, 'gateway') || str_contains($text, 'sorting')) {
            return 'in_transit';
        }
        if (str_contains($text, 'picked') || str_contains($text, 'pickup') || str_contains($text, 'diambil') || str_contains($text, 'dijemput') || str_contains($text, 'manifested')) {
            return 'picked_up';
        }
        if (str_contains($text, 'packed') || str_contains($text, 'dikemas') || str_contains($text, 'packing') || str_contains($text, 'order dibuat') || str_contains($text, 'resi dibuat') || str_contains($text, 'shipment received')) {
            return 'packed';
        }
        return $events !== [] || $status !== '' || $desc !== '' ? 'in_transit' : 'pending';
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