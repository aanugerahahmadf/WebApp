<?php

namespace App\Filament\Admin\Exports\PaymentMethodExporter;

use App\Models\PaymentMethod\PaymentMethod;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class PaymentMethodExporter extends Exporter
{
    protected static ?string $model = PaymentMethod::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label(__('ID')),
            ExportColumn::make('name')
                ->label(__('Nama Metode')),
            ExportColumn::make('type')
                ->label(__('Tipe')),
            ExportColumn::make('code')
                ->label(__('Kode')),
            ExportColumn::make('account_number')
                ->label(__('Nomor Akun')),
            ExportColumn::make('account_holder')
                ->label(__('Nama Pemilik')),
            ExportColumn::make('fee')
                ->label(__('Biaya')),
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
        $body = 'Ekspor metode pembayaran telah selesai dan '.number_format($export->successful_rows).' '.str('baris')->plural($export->successful_rows).' berhasil diekspor.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('baris')->plural($failedRowsCount).' gagal diekspor.';
        }

        return $body;
    }
}
