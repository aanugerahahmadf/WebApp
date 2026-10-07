<?php

namespace App\Filament\Admin\Resources\WeddingDecorationPolicyResource\Pages\ManageWeddingDecorationPolicies;

use App\Filament\Admin\Concerns\HasMobilePagination\HasMobilePagination;
use App\Filament\Admin\Exports\WeddingDecorationPolicyExporter\WeddingDecorationPolicyExporter;
use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Resources\WeddingDecorationPolicyResource\WeddingDecorationPolicyResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageWeddingDecorationPolicies extends ManageRecords
{
    use HasMobilePagination;
    use HasDynamicBreadcrumbs;
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
                ->icon('heroicon-s-arrow-down-tray')
                ->color('success'),
            Actions\CreateAction::make()
                ->label(__('Tambah Kebijakan'))
                ->icon('heroicon-s-plus')
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
            ...$this->breadcrumbParentCrumb(),
            WeddingDecorationPolicyResource::getUrl('index') => WeddingDecorationPolicyResource::getNavigationLabel(),
        ];
    }
}
