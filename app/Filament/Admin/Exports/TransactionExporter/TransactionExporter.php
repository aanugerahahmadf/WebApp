<?php

namespace App\Filament\Admin\Exports\TransactionExporter;

use App\Models\Transaction\Transaction;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class TransactionExporter extends Exporter
{
    protected static ?string $model = Transaction::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label(__('ID')),
            ExportColumn::make('user_id')
                ->label(__('ID Pengguna')),
            ExportColumn::make('order_id')
                ->label(__('ID Pesanan')),
            ExportColumn::make('type')
                ->label(__('Tipe')),
            ExportColumn::make('reference_number')
                ->label(__('No. Referensi')),
            ExportColumn::make('amount')
                ->label(__('Nominal')),
            ExportColumn::make('admin_fee')
                ->label(__('Biaya Admin')),
            ExportColumn::make('total_amount')
                ->label(__('Total')),
            ExportColumn::make('payment_gateway')
                ->label(__('Gateway')),
            ExportColumn::make('payment_method')
                ->label(__('Metode')),
            ExportColumn::make('payment_method_id')
                ->label(__('ID Metode')),
            ExportColumn::make('payment_url')
                ->label(__('URL Bayar')),
            ExportColumn::make('virtual_account_no')
                ->label(__('No. VA')),
            ExportColumn::make('virtual_account_expiry')
                ->label(__('VA Kedaluwarsa')),
            ExportColumn::make('status')
                ->label(__('Status')),
            ExportColumn::make('paid_at')
                ->label(__('Dibayar Pada')),
            ExportColumn::make('notes')
                ->label(__('Catatan')),
            ExportColumn::make('created_at')
                ->label(__('Dibuat Pada')),
            ExportColumn::make('updated_at')
                ->label(__('Diperbarui Pada')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your transaction export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
