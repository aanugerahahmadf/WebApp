<?php

namespace App\Filament\Admin\Resources\PrivacyPolicyResource\Pages\ManagePrivacyPolicies;

use App\Filament\Admin\Exports\PrivacyPolicyExporter\PrivacyPolicyExporter;
use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Resources\PrivacyPolicyResource\PrivacyPolicyResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManagePrivacyPolicies extends ManageRecords
{
    protected static string $resource = PrivacyPolicyResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(PrivacyPolicyExporter::class)
                ->label(__('Ekspor Data'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success'),
            Actions\CreateAction::make()
                ->label(__('Tambah Kebijakan Privasi'))
                ->icon('heroicon-o-plus')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title(__('Kebijakan Privasi Ditambahkan'))
                        ->body(__('Kebijakan privasi baru telah berhasil ditambahkan.'))
                ),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            Home::getUrl() => __('Beranda'),
            PrivacyPolicyResource::getUrl('index') => PrivacyPolicyResource::getNavigationLabel(),
        ];
    }
}
