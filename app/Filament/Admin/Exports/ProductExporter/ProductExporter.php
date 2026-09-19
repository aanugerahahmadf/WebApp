<?php

namespace App\Filament\Admin\Exports\ProductExporter;

use App\Models\Product\Product;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ProductExporter extends Exporter
{
    protected static ?string $model = Product::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label(__('ID')),
            ExportColumn::make('name')
                ->label(__('Nama Product')),
            ExportColumn::make('slug')
                ->label(__('Slug')),
            ExportColumn::make('price')
                ->label(__('Harga')),
            ExportColumn::make('discount_price')
                ->label(__('Harga Diskon')),
            ExportColumn::make('stock')
                ->label(__('Stok')),
            ExportColumn::make('category.name')
                ->label(__('Kategori')),
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
        $body = 'Ekspor product telah selesai dan '.number_format($export->successful_rows).' '.str('baris')->plural($export->successful_rows).' berhasil diekspor.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('baris')->plural($failedRowsCount).' gagal diekspor.';
        }

        return $body;
    }
}
