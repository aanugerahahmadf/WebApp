<?php

namespace App\Filament\Admin\Resources\PaymentMethodResource\Pages\ManagePaymentMethods;

use App\Filament\Admin\Concerns\HasMobilePagination\HasMobilePagination;
use App\Filament\Admin\Exports\PaymentMethodExporter\PaymentMethodExporter;
use App\Filament\Admin\Pages\Home\Home;
use App\Filament\Admin\Resources\PaymentMethodResource\PaymentMethodResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManagePaymentMethods extends ManageRecords
{
    use HasMobilePagination;
    use HasDynamicBreadcrumbs;
    protected static string $resource = PaymentMethodResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ExportAction::make()
                ->exporter(PaymentMethodExporter::class)
                ->label(__('Ekspor Data'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success'),
            Actions\CreateAction::make()
                ->label(__('Tambah Metode Pembayaran'))
                ->icon('heroicon-o-plus')
                ->mutateFormDataUsing(function (array $data): array {
                    if (empty($data['sort_order'])) {
                        $data['sort_order'] = PaymentMethodResource::getModel()::max('sort_order') + 1;
                    }
                    return $data;
                })
                ->successNotification(
                    Notification::make()
                        ->success()
                        ->title(__('Metode Pembayaran Ditambahkan'))
                        ->body(__('Metode pembayaran baru berhasil ditambahkan.'))
                ),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            PaymentMethodResource::getUrl('index') => PaymentMethodResource::getNavigationLabel(),
        ];
    }
}
