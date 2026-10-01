<?php

namespace App\Filament\Admin\Exports\ReferenceOptionExporter;

use App\Models\ReferenceOption\ReferenceOption;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ReferenceOptionExporter extends Exporter
{
    protected static ?string $model = ReferenceOption::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label(__('ID')),
            ExportColumn::make('type')
                ->label(__('Tipe')),
            ExportColumn::make('key')
                ->label(__('Kunci')),
            ExportColumn::make('label')
                ->label(__('Label')),
            ExportColumn::make('sort_order')
                ->label(__('Urutan')),
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
        $body = 'Your reference option export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
