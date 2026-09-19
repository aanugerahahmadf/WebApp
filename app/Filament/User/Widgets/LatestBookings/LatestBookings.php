<?php

namespace App\Filament\User\Widgets\LatestBookings;

use App\Models\Order\Order;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Auth;

class LatestBookings extends BaseWidget
{
    protected static ?int $navigationSort = 5;

    protected function getTableHeading(): string|Htmlable|null
    {
        return __('Pesanan Terakhir');
    }

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Order::query()->where('user_id', Auth::id())->latest()->limit(6)
            )
            ->contentGrid([
                'default' => 2,
                'xl' => 3,
            ])
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\ImageColumn::make('package.image_url')
                        ->label('')
                        ->height('12rem')
                        ->width('100%')
                        ->extraImgAttributes([
                            'class' => 'rounded-xl object-cover shadow-sm',
                        ]),
                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\Layout\Split::make([
                            Tables\Columns\TextColumn::make('package.name')
                                ->weight(FontWeight::Bold)
                                ->color('primary')
                                ->size('lg')
                                ->grow(false),
                            Tables\Columns\TextColumn::make('status')
                                ->badge()
                                ->alignEnd(),
                        ]),
                        Tables\Columns\TextColumn::make('order_number')
                            ->weight(FontWeight::Bold)
                            ->size('sm')
                            ->icon('heroicon-m-hashtag')
                            ->color('gray'),
                        Tables\Columns\Layout\Split::make([
                            Tables\Columns\TextColumn::make('total_text')
                                ->state(fn () => __('Total:'))
                                ->size('sm')
                                ->color('gray')
                                ->alignEnd(),
                            Tables\Columns\TextColumn::make('total_price')
                                ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 0, ',', '.'))
                                ->weight(FontWeight::Bold)
                                ->color('success')
                                ->grow(false)
                                ->size('md'),
                        ])->extraAttributes(['class' => 'mt-2 pt-2 border-t border-gray-100 dark:border-gray-800']),
                    ])->space(1)->extraAttributes(['class' => 'p-3 bg-gray-50 dark:bg-gray-900 rounded-xl mt-3']),
                ])->extraAttributes(fn ($record) => [
                    'class' => 'p-4 bg-white dark:bg-gray-950 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 transition-all hover:shadow-md cursor-pointer',
                    'onclick' => "window.location.href='".route('filament.user.resources.orders.index', ['tableFilters[id][value]' => $record->id])."'",
                ]),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('Belum ada pesanan'))
            ->emptyStateDescription(__('Mulai rencanakan pernikahan Anda sekarang.'))
            ->emptyStateIcon('heroicon-o-shopping-bag');
    }
}
