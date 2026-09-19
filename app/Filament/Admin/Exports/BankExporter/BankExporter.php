<?php

namespace App\Filament\Admin\Exports\BankExporter;

use App\Models\Bank\Bank;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class BankExporter extends Exporter
{
    protected static ?string $model = Bank::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label(__('ID')),
            ExportColumn::make('name')
                ->label(__('Nama')),
            ExportColumn::make('code')
                ->label(__('Kode')),
            ExportColumn::make('type')
                ->label(__('Tipe')),
            ExportColumn::make('logo')
                ->label(__('Logo')),
            ExportColumn::make('qris_payload')
                ->label(__('Payload QRIS')),
            ExportColumn::make('qris_image')
                ->label(__('Gambar QRIS')),
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
        $body = 'Your bank export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
