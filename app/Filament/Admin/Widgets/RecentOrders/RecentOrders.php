<?php

namespace App\Filament\Admin\Widgets\RecentOrders;

use App\Models\Order\Order;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class RecentOrders extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public function getTableHeading(): string
    {
        return __('Pesanan Terbaru');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn (): Builder => Order::query()->latest()->limit(5)
            )
            ->paginated([5])
            ->columns([
                Tables\Columns\TextColumn::make('order_number')
                    ->label(__('No. Pesanan'))
                    ->searchable()
                    ->weight('bold'),
                Tables\Columns\TextColumn::make('user.full_name')
                    ->label(__('Pelanggan')),
                Tables\Columns\TextColumn::make('package.name')
                    ->label(__('Paket Dekorasi'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('total_price')
                    ->label(__('Total Harga'))
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 0, ',', '.'))
                    ->alignEnd(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->label(__('Tanggal Terdaftar'))
                    ->toggleable(isToggledHiddenByDefault: true),
            ]);
    }
}
