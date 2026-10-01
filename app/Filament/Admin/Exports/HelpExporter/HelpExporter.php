<?php

namespace App\Filament\Admin\Exports\HelpExporter;

use App\Models\Help\Help;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class HelpExporter extends Exporter
{
    protected static ?string $model = Help::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label(__('ID')),
            ExportColumn::make('title')
                ->label(__('Judul')),
            ExportColumn::make('subtitle')
                ->label(__('Subjudul')),
            ExportColumn::make('faqs')
                ->label(__('FAQ')),
            ExportColumn::make('contact_options')
                ->label(__('Kontak')),
            ExportColumn::make('created_at')
                ->label(__('Dibuat Pada')),
            ExportColumn::make('updated_at')
                ->label(__('Diperbarui Pada')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your help export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
