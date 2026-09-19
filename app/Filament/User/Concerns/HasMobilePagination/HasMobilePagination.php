<?php

namespace App\Filament\User\Concerns\HasMobilePagination;

trait HasMobilePagination
{
    protected function isMobileRequest(): bool
    {
        $userAgent = request()->userAgent() ?? '';

        return (bool) preg_match(
            '/android|iphone|ipad|ipod|mobile|blackberry|windows phone/i',
            $userAgent
        );
    }

    public function getDefaultTableRecordsPerPageSelectOption(): int
    {
        return $this->isMobileRequest() ? 20 : 10;
    }

    public function getTableRecordsPerPageSelectOptions(): array
    {
        if ($this->isMobileRequest()) {
            return [20];
        }

        return [10, 25, 50, 100];
    }
}
