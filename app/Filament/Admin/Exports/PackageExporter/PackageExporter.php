<?php

namespace App\Filament\Admin\Exports\PackageExporter;

use App\Models\Package\Package;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class PackageExporter extends Exporter
{
    protected static ?string $model = Package::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label(__('ID')),

            ExportColumn::make('category_id')
                ->label(__('ID Kategori')),
            ExportColumn::make('vendor_id')
                ->label(__('ID Vendor')),
            ExportColumn::make('name')
                ->label(__('Nama Paket')),
            ExportColumn::make('slug')
                ->label(__('Slug')),
            ExportColumn::make('description')
                ->label(__('Deskripsi')),
            ExportColumn::make('price')
                ->label(__('Harga')),
            ExportColumn::make('features')
                ->label(__('Fitur')),
            ExportColumn::make('theme')
                ->label(__('Tema')),
            ExportColumn::make('color')
                ->label(__('Warna')),
            ExportColumn::make('min_capacity')
                ->label(__('Kapasitas Min')),
            ExportColumn::make('max_capacity')
                ->label(__('Kapasitas Max')),
            ExportColumn::make('created_at')
                ->label(__('Dibuat Pada')),
            ExportColumn::make('updated_at')
                ->label(__('Diperbarui Pada')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your package export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
