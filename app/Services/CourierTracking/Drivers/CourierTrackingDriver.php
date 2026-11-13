<?php

namespace App\Services\CourierTracking\Drivers;

use App\Services\CourierTracking\CourierTrackingResult;

interface CourierTrackingDriver
{
    public function track(string $courier, string $trackingNumber): CourierTrackingResult;

    /** @return array<int, string> internal courier codes supported */
    public function supports(): array;
}