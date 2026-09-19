<?php

namespace App\Filament\User\Widgets\UnifiedHistoryWidget;

use App\Models\Transaction\Transaction;
use Filament\Facades\Filament;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;

class UnifiedHistoryWidget extends BaseWidget
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected function getTableHeading(): string|Htmlable|null
    {
        return __('Riwayat Aktivitas Terakhir');
    }

    public function table(Table $table): Table
    {
        $userId = Filament::auth()->id();

        return $table
            ->query(
                Transaction::where('user_id', $userId)
                    ->where('type', 'order')
                    ->latest()
            )
            ->columns([
                Tables\Columns\TextColumn::make('ref')
                    ->label(__('ID Transaksi'))
                    ->weight('bold')
                    ->color('gray')
                    ->icon('heroicon-m-shopping-bag')
                    ->iconColor('info'),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('Tipe'))
                    ->badge()
                    ->formatStateUsing(fn () => __('Beli'))
                    ->color('info'),
                Tables\Columns\TextColumn::make('total_amount')
                    ->label(__('Nominal'))
                    ->formatStateUsing(fn ($state) => '- Rp '.number_format($state, 0, ',', '.'))
                    ->color('danger')
                    ->weight('black'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(function ($state) {
                        $val = $state instanceof \BackedEnum ? $state->value : (string) $state;

                        return match ($val) {
                            'pending' => __('Proses'),
                            'success', 'completed', 'approved', 'paid' => __('Selesai'),
                            'failed', 'rejected', 'cancelled' => __('Gagal'),
                            default => ucfirst($val),
                        };
                    })
                    ->color(function ($state) {
                        $val = $state instanceof \BackedEnum ? $state->value : (string) $state;

                        return match ($val) {
                            'pending' => 'warning',
                            'success', 'completed' => 'success',
                            'failed', 'rejected' => 'danger',
                            default => 'gray',
                        };
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Waktu'))
                    ->dateTime('d/m/y H:i')
                    ->color('gray')
                    ->size('xs'),
            ])
            ->actions([
                Tables\Actions\Action::make('details')
                    ->label(__('Lihat'))
                    ->icon('heroicon-m-eye')
                    ->button()
                    ->size('xs')
                    ->color('gray')
                    ->url(function ($record) {
                        return $record->order_id
                            ? route('filament.user.resources.orders.view', ['record' => $record->order_id])
                            : route('filament.user.resources.orders.index');
                    }),
            ]);
    }
}
