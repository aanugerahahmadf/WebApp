<?php

namespace App\Filament\User\Resources\CartResource\Pages\ManageCarts;

use App\Filament\User\Concerns\HasMobilePagination\HasMobilePagination;
use App\Filament\User\Resources\CartResource\CartResource;
use Filament\Resources\Pages\ManageRecords;

class ManageCarts extends ManageRecords
{
    use HasMobilePagination;

    protected static string $resource = CartResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
