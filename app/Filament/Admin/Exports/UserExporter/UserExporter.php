<?php

namespace App\Filament\Admin\Exports\UserExporter;

use App\Models\User\User;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class UserExporter extends Exporter
{
    protected static ?string $model = User::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('id')
                ->label(__('ID')),
            ExportColumn::make('full_name')
                ->label(__('Nama Lengkap')),
            ExportColumn::make('username')
                ->label(__('Username')),
            ExportColumn::make('email')
                ->label(__('Email')),
            ExportColumn::make('email_verified_at')
                ->label(__('Email Diverifikasi Pada')),
            ExportColumn::make('first_name')
                ->label(__('Nama Depan')),
            ExportColumn::make('last_name')
                ->label(__('Nama Belakang')),
            ExportColumn::make('phone')
                ->label(__('Telepon')),
            ExportColumn::make('address')
                ->label(__('Alamat')),
            ExportColumn::make('gender')
                ->label(__('Jenis Kelamin')),
            ExportColumn::make('religion')
                ->label(__('Agama')),
            ExportColumn::make('marital_status')
                ->label(__('Status Pernikahan')),
            ExportColumn::make('mother_name')
                ->label(__('Nama Ibu Kandung')),
            ExportColumn::make('occupation')
                ->label(__('Pekerjaan')),
            ExportColumn::make('income_range')
                ->label(__('Rentang Penghasilan')),
            ExportColumn::make('source_of_funds')
                ->label(__('Sumber Dana')),
            ExportColumn::make('active_status')
                ->label(__('Status Aktif')),
            ExportColumn::make('created_at')
                ->label(__('Dibuat Pada')),
            ExportColumn::make('updated_at')
                ->label(__('Diperbarui Pada')),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your user export has completed and '.number_format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.number_format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
