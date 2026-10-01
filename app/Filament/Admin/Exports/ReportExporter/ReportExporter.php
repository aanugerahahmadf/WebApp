<?php

namespace App\Filament\Admin\Exports\ReportExporter;

use App\Models\Report\Report;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ReportExporter extends Exporter
{
    protected static ?string $model = Report::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label(__('ID')),
            ExportColumn::make('user_id')
                ->label(__('ID Pengguna')),
            ExportColumn::make('reportable_type')
                ->label(__('Tipe Terlapor')),
            ExportColumn::make('reportable_id')
                ->label(__('ID Terlapor')),
            ExportColumn::make('category')
                ->label(__('Kategori')),
            ExportColumn::make('reason')
                ->label(__('Judul')),
            ExportColumn::make('description')
                ->label(__('Detail')),
            ExportColumn::make('attachments')
                ->label(__('Lampiran')),
            ExportColumn::make('status')
                ->label(__('Status')),
            ExportColumn::make('resolved_at')
                ->label(__('Diselesaikan')),
            ExportColumn::make('resolved_by')
                ->label(__('Oleh')),
            ExportColumn::make('created_at')
                ->label(__('Dibuat Pada')),
            ExportColumn::make('updated_at')
                ->label(__('Diperbarui Pada')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your report export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
