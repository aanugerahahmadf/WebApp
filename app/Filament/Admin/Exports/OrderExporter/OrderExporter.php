<?php

namespace App\Filament\Admin\Exports\OrderExporter;

use App\Models\Order\Order;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class OrderExporter extends Exporter
{
    protected static ?string $model = Order::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label(__('ID')),
            ExportColumn::make('user_id')
                ->label(__('ID Pengguna')),
            ExportColumn::make('package_id')
                ->label(__('ID Paket')),
            ExportColumn::make('order_number')
                ->label(__('Nomor Pesanan')),
            ExportColumn::make('total_price')
                ->label(__('Total Harga')),
            ExportColumn::make('status')
                ->label(__('Status')),
            ExportColumn::make('payment_status')
                ->label(__('Status Pembayaran')),
            ExportColumn::make('booking_date')
                ->label(__('Tanggal Booking')),
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
        $body = 'Your order export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
