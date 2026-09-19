<?php

namespace App\Filament\Admin\Exports\VoucherExporter;

use App\Models\Voucher\Voucher;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class VoucherExporter extends Exporter
{
    protected static ?string $model = Voucher::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label(__('ID')),
            ExportColumn::make('code')
                ->label(__('Kode')),
            ExportColumn::make('description')
                ->label(__('Deskripsi')),
            ExportColumn::make('discount_amount')
                ->label(__('Jumlah Diskon')),
            ExportColumn::make('discount_type')
                ->label(__('Tipe Diskon')),
            ExportColumn::make('min_purchase')
                ->label(__('Min Pembelian')),
            ExportColumn::make('expires_at')
                ->label(__('Kedaluwarsa Pada')),
            ExportColumn::make('is_active')
                ->label(__('Status Aktif')),
            ExportColumn::make('created_at')
                ->label(__('Dibuat Pada')),
            ExportColumn::make('updated_at')
                ->label(__('Diperbarui Pada')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your voucher export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
