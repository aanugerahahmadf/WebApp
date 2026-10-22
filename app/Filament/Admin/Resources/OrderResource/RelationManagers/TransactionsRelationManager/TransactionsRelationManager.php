<?php

namespace App\Filament\Admin\Resources\OrderResource\RelationManagers\TransactionsRelationManager;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Tab "Transaksi" di halaman pesanan.
 */
class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Transaksi';

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                TextColumn::make('id')->label(__('ID')),
                TextColumn::make('type')->label(__('Jenis')),
                TextColumn::make('amount')->label(__('Jumlah'))->money('IDR'),
                TextColumn::make('created_at')->label(__('Dibuat'))->dateTime(),
            ])
            ->paginated(false)
            // Diurutkan NAIK: ID 1 jadi baris PERTAMA, sama seperti seluruh
            // tabel admin lain.
            ->defaultSort('id');
    }
}
