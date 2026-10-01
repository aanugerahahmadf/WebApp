<?php

namespace App\Filament\Admin\Exports\CartExporter;

use App\Models\Cart\Cart;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class CartExporter extends Exporter
{
    protected static ?string $model = Cart::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label(__('ID')),
            ExportColumn::make('user_id')
                ->label(__('ID Pengguna')),
            ExportColumn::make('product_id')
                ->label(__('ID Produk')),
            ExportColumn::make('package_id')
                ->label(__('ID Paket')),
            ExportColumn::make('quantity')
                ->label(__('Jumlah')),
            ExportColumn::make('meta')
                ->label(__('Meta')),
            ExportColumn::make('created_at')
                ->label(__('Dibuat Pada')),
            ExportColumn::make('updated_at')
                ->label(__('Diperbarui Pada')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your cart export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
