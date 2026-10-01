<?php

namespace App\Filament\Admin\Exports\VendorExporter;

use App\Models\Vendor\Vendor;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class VendorExporter extends Exporter
{
    protected static ?string $model = Vendor::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label(__('ID')),
            ExportColumn::make('user_id')
                ->label(__('ID Pengguna')),
            ExportColumn::make('store_name')
                ->label(__('Nama Toko')),
            ExportColumn::make('contact_person')
                ->label(__('Kontak')),
            ExportColumn::make('no_telp')
                ->label(__('No. Telepon')),
            ExportColumn::make('store_description')
                ->label(__('Deskripsi')),
            ExportColumn::make('logo')
                ->label(__('Logo')),
            ExportColumn::make('is_active')
                ->label(__('Aktif')),
            ExportColumn::make('is_partner')
                ->label(__('Partner')),
            ExportColumn::make('created_at')
                ->label(__('Dibuat Pada')),
            ExportColumn::make('updated_at')
                ->label(__('Diperbarui Pada')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your vendor export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
