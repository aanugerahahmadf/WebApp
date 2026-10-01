<?php

namespace App\Filament\Admin\Exports\PaymentGatewayExporter;

use App\Models\PaymentGateway\PaymentGateway;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class PaymentGatewayExporter extends Exporter
{
    protected static ?string $model = PaymentGateway::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label(__('ID')),
            ExportColumn::make('name')
                ->label(__('Nama')),
            ExportColumn::make('code')
                ->label(__('Kode')),
            ExportColumn::make('description')
                ->label(__('Deskripsi')),
            ExportColumn::make('is_active')
                ->label(__('Aktif')),
            ExportColumn::make('created_at')
                ->label(__('Dibuat Pada')),
            ExportColumn::make('updated_at')
                ->label(__('Diperbarui Pada')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your payment gateway export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
