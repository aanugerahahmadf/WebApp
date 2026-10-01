<?php

namespace App\Filament\User\Resources\ProductResource\Pages\ViewProduct;

use App\Filament\User\Pages\CbirSearchPage\CbirSearchPage;
use App\Filament\User\Pages\Home\Home;
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

    public function getBreadcrumbs(): array
    {
        return [
            Home::getUrl() => __('Beranda'),
            ...parent::getBreadcrumbs(),
        ];
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

                    return Home::getUrl();
                })
                ->color('gray')->button()
                ->icon('heroicon-o-arrow-left'),
        ];
    }
}
