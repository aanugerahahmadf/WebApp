<?php

namespace App\Filament\Welcome\Resources\VoucherResource\Pages\ManageVouchers;

use App\Filament\Welcome\Resources\VoucherResource\VoucherResource;
use Filament\Resources\Pages\ManageRecords;

class ManageVouchers extends ManageRecords
{
    protected static string $resource = VoucherResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // No creation action for user
        ];
    }

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }
}
