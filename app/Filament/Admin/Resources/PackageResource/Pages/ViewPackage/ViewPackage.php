<?php

namespace App\Filament\Admin\Resources\PackageResource\Pages\ViewPackage;

use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Resources\PackageResource\PackageResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPackage extends ViewRecord
{
    protected static string $resource = PackageResource::class;

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
                ->url(fn () => static::getResource()::getUrl('index'))
                ->color('gray')->button()
                ->icon('heroicon-s-arrow-left'),

            Actions\EditAction::make(),
        ];
    }
}
