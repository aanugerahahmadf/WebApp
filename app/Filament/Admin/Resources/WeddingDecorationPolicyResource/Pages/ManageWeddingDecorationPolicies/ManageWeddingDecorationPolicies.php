<?php

namespace App\Filament\Admin\Resources\WeddingDecorationPolicyResource\Pages\ManageWeddingDecorationPolicies;

use App\Filament\Admin\Exports\WeddingDecorationPolicyExporter\WeddingDecorationPolicyExporter;
use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Resources\WeddingDecorationPolicyResource\WeddingDecorationPolicyResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageWeddingDecorationPolicies extends ManageRecords
{
    protected static string $resource = WeddingDecorationPolicyResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(WeddingDecorationPolicyExporter::class)
                ->label(__('Ekspor Data'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success'),
            Actions\CreateAction::make()
                ->label(__('Tambah Kebijakan'))
                ->icon('heroicon-o-plus')
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title(__('Kebijakan Ditambahkan'))
                        ->body(__('Kebijakan baru telah berhasil ditambahkan.'))
                ),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            Home::getUrl() => __('Beranda'),
            WeddingDecorationPolicyResource::getUrl('index') => WeddingDecorationPolicyResource::getNavigationLabel(),
        ];
    }
}
