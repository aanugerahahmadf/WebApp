<?php

namespace App\Filament\Admin\Widgets\RecentOrders;

use App\Models\Order\Order;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

/**
 * Pesanan terakhir yang masuk.
 */
class RecentOrders extends TableWidget
{
    protected static ?string $heading = null;

    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';
    /**
     * Widget Filament LAZY secara bawaan: isinya diambil lewat request
     * Livewire terpisah saat widget masuk viewport, dan HTML awal cuma
     * berisi skeleton. Untuk dashboard admin, angkanya harus LANGSUNG
     * terlihat -- jadi lazy dimatikan.
     */
    protected static bool $isLazy = false;

    public function getHeading(): ?string
    {
        return __('Pesanan Terbaru');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Order::query()->latest('created_at')->limit(10))
            ->columns([
                TextColumn::make('order_number')->label(__('No. Pesanan'))->searchable(),
                TextColumn::make('user.name')->label(__('Pelanggan')),
                TextColumn::make('total_price')->label(__('Total'))->money('IDR'),
                TextColumn::make('payment_status')->label(__('Status Pembayaran'))->badge(),
                TextColumn::make('created_at')->label(__('Dibuat'))->since()->dateTimeTooltip(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
