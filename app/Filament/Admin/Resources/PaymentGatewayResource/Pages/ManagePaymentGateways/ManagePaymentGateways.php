<?php

namespace App\Filament\Admin\Resources\PaymentGatewayResource\Pages\ManagePaymentGateways;

use App\Filament\Admin\Exports\PaymentGatewayExporter\PaymentGatewayExporter;
use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Resources\PaymentGatewayResource\PaymentGatewayResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Actions;
use Filament\Resources\Pages\ManageRecords;

class ManagePaymentGateways extends ManageRecords
{
    use HasDynamicBreadcrumbs;
    protected static string $resource = PaymentGatewayResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(PaymentGatewayExporter::class)
                ->label(__('Ekspor Data'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success'),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            PaymentGatewayResource::getUrl('index') => PaymentGatewayResource::getNavigationLabel(),
        ];
    }
}
