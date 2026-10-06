<?php

namespace App\Filament\Admin\Resources\TransactionResource\Pages\ListTransactions;

use App\Filament\Admin\Concerns\HasMobilePagination\HasMobilePagination;
use App\Filament\Admin\Exports\TransactionExporter\TransactionExporter;
use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Resources\TransactionResource\TransactionResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTransactions extends ListRecords
{
    use HasMobilePagination;
    use HasDynamicBreadcrumbs;
    protected static string $resource = TransactionResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            TransactionResource::getUrl('index') => TransactionResource::getNavigationLabel(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(TransactionExporter::class)
                ->label(__('Ekspor Data'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success'),
            Actions\CreateAction::make(),
        ];
    }
}
