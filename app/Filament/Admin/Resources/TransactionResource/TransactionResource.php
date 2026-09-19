<?php

namespace App\Filament\Admin\Resources\TransactionResource;

use App\Models\Transaction\Transaction;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TransactionResource extends Resource
{
    protected static ?string $model = Transaction::class;

    protected static ?string $slug = 'transactions';

    protected static ?string $navigationIcon = 'heroicon-o-banknotes';

    public static function getNavigationGroup(): ?string
    {
        return __('Transaksi');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function getModelLabel(): string
    {
        return __('Transaksi');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Transaksi');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Readonly or no form needed since it's just for viewing
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([5])
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reference_number')
                    ->label(__('Referensi'))
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyableState(fn ($state) => $state)
                    ->icon('heroicon-o-document-text'),
                TextColumn::make('user.full_name')
                    ->label(__('Pengguna'))
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-user'),
                TextColumn::make('type')
                    ->label(__('Tipe'))
                    ->badge()
                    ->alignment('center')
                    ->sortable(),
                TextColumn::make('total_amount')
                    ->label(__('Jumlah'))
                    ->money('idr')
                    ->sortable()
                    ->alignment('end')
                    ->icon('heroicon-o-banknotes'),
                TextColumn::make('payment_gateway')
                    ->label(__('Gateway Pembayaran'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'manual' => 'success',
                        'stripe' => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable()
                    ->alignment('center'),
                TextColumn::make('payment_method')
                    ->label(__('Metode'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'credit_card' => 'info',
                        'bank_transfer' => 'warning',
                        'e_wallet' => 'success',
                        default => 'gray',
                    })
                    ->searchable()
                    ->alignment('center'),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->alignment('center')
                    ->sortable(),
                TextColumn::make('paid_at')
                    ->label(__('Waktu Pembayaran'))
                    ->dateTime()
                    ->sortable()
                    ->alignment('center')
                    ->icon('heroicon-o-calendar')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('Dibuat Pada'))
                    ->dateTime()
                    ->sortable()
                    ->alignment('center')
                    ->icon('heroicon-o-calendar'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options([
                        'order' => 'Order',
                        'topup' => 'Topup',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'success' => 'Success',
                        'failed' => 'Failed',
                        'expired' => 'Expired',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTransactions\ListTransactions::route('/'),
        ];
    }
}
