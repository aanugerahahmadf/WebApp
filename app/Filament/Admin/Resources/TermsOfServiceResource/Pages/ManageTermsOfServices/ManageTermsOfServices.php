<?php

namespace App\Filament\Admin\Resources\TermsOfServiceResource\Pages\ManageTermsOfServices;

use App\Filament\Admin\Resources\TermsOfServiceResource\TermsOfServiceResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageTermsOfServices extends ManageRecords
{
    protected static string $resource = TermsOfServiceResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label(__('Tambah Ketentuan Layanan'))
                ->icon('heroicon-o-plus')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title(__('Ketentuan Layanan Ditambahkan'))
                        ->body(__('Ketentuan layanan baru telah berhasil ditambahkan.'))
                ),
        ];
    }
}
