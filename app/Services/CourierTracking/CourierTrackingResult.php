<?php

namespace App\Services\CourierTracking;

class CourierTrackingResult
{
    /**
     * @param  array<int, array{time: ?string, status: ?string, description: string, location: ?string, raw: array}>  $events
     */
    public function __construct(
        public bool $success,
        public ?string $courier,
        public ?string $trackingNumber,
        public ?string $status,
        public ?string $summary,
        public array $events = [],
        public ?string $error = null,
        public array $raw = [],
    ) {}

    public static function failure(string $error, ?string $courier = null, ?string $trackingNumber = null): self
    {
        return new self(false, $courier, $trackingNumber, null, null, [], $error);
    }
}