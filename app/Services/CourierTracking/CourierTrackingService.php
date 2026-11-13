<?php

namespace App\Services\CourierTracking;

use App\Services\CourierTracking\Drivers\BinderByteDriver;
use App\Services\CourierTracking\Drivers\BiteshipDriver;
use App\Services\CourierTracking\Drivers\CourierTrackingDriver;

class CourierTrackingService
{
    public function driver(?string $name = null): CourierTrackingDriver
    {
        $name = $name ?: (string) config('services.courier_tracking.driver', 'binderbyte');

        return match ($name) {
            'biteship' => new BiteshipDriver(
                (string) config('services.courier_tracking.biteship.api_key', ''),
                (string) config('services.courier_tracking.biteship.base_url', 'https://api.biteship.com/v1'),
                (int) config('services.courier_tracking.timeout', 15),
            ),
            default => new BinderByteDriver(
                (string) config('services.courier_tracking.binderbyte.api_key', ''),
                (string) config('services.courier_tracking.binderbyte.base_url', 'https://api.binderbyte.com/v1/track'),
                (int) config('services.courier_tracking.timeout', 15),
            ),
        };
    }

    public function track(?string $courier, ?string $trackingNumber): CourierTrackingResult
    {
        $courier = trim((string) $courier);
        $trackingNumber = trim((string) $trackingNumber);
        if ($courier === '' || $trackingNumber === '') {
            return CourierTrackingResult::failure('Kurir dan nomor resi wajib diisi.', $courier ?: null, $trackingNumber ?: null);
        }

        $driverName = (string) config('services.courier_tracking.driver', 'binderbyte');
        if ($driverName === 'manual' || $driverName === 'none') {
            return CourierTrackingResult::failure('Sinkronisasi pelacakan otomatis dinonaktifkan (mode manual).', $courier, $trackingNumber);
        }

        return $this->driver($driverName)->track($courier, $trackingNumber);
    }
}