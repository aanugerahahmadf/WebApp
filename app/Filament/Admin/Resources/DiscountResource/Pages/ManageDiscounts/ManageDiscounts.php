<?php

namespace App\Filament\Admin\Resources\DiscountResource\Pages\ManageDiscounts;

use App\Filament\Admin\Resources\DiscountResource\DiscountResource;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManageDiscounts extends ManageRecords
{
    protected static string $resource = DiscountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('Tambah Diskon'))
                ->icon('heroicon-o-plus'),
        ];
    }
}
