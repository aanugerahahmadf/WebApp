<?php

namespace App\Filament\User\Resources\ProductResource\Pages\ViewProduct;

use App\Filament\User\Pages\CbirSearchPage\CbirSearchPage;
use App\Filament\User\Pages\Dashboard\Dashboard;
use App\Filament\User\Resources\ProductResource\ProductResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('back')
                ->label(__('Kembali'))
                ->url(function () {
                    $prev = url()->previous();
                    if (str_contains($prev, 'cbir-search')) {
                        return CbirSearchPage::getUrl();
                    }
                    if (str_contains($prev, 'packages') || str_contains($prev, 'products')) {
                        return static::getResource()::getUrl('index');
                    }

                    return Dashboard::getUrl();
                })
                ->color('gray')->button()
                ->icon('heroicon-o-arrow-left'),
        ];
    }
}
