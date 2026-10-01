<?php

namespace App\Filament\Admin\Exports\DiscountExporter;

use App\Models\Discount\Discount;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class DiscountExporter extends Exporter
{
    protected static ?string $model = Discount::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label(__('ID')),
            ExportColumn::make('description')
                ->label(__('Deskripsi')),
            ExportColumn::make('discountable_type')
                ->label(__('Tipe Target')),
            ExportColumn::make('discountable_id')
                ->label(__('ID Target')),
            ExportColumn::make('type')
                ->label(__('Tipe')),
            ExportColumn::make('value')
                ->label(__('Nilai')),
            ExportColumn::make('min_purchase')
                ->label(__('Min. Belanja')),
            ExportColumn::make('start_date')
                ->label(__('Mulai')),
            ExportColumn::make('end_date')
                ->label(__('Selesai')),
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
        $body = 'Your discount export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
