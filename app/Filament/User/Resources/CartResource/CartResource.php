<?php

namespace App\Filament\User\Resources\CartResource;

use App\Filament\User\Resources\CartResource\Pages;
use App\Filament\User\Resources\PackageResource\PackageResource;
use App\Filament\User\Resources\ProductResource\ProductResource;
use App\Helpers\NativeNotificationHelper\NativeNotificationHelper;
use App\Models\Cart\Cart;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Component;

class CartResource extends Resource
{
    protected static ?string $model = Cart::class;

    protected static ?string $slug = 'carts';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    protected static ?int $navigationSort = 1;

    public static function getNavigationLabel(): string
    {
        return __('Keranjang');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Keranjang');
    }

    public static function getModelLabel(): string
    {
        return __('Keranjang');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['product.name', 'package.name', 'product.category.name', 'package.category.name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->package?->name ?? $record->product?->name ?? __('Item Keranjang');
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            __('Jenis') => $record->package_id ? __('Paket') : __('Produk'),
            __('Jumlah') => $record->quantity ?? 1,
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('index');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Transaksi & Aktivitas');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::where('user_id', Filament::auth()->id())->count();
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return static::getNavigationLabel();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('quantity')
                    ->numeric()
                    ->required()
                    ->minValue(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('Keranjang Kosong'))
            ->emptyStateDescription(__('Mulai belanja dan temukan dekorasi impian Anda sekarang!'))
            ->emptyStateIcon('heroicon-o-shopping-cart')
            ->emptyStateActions([
                Tables\Actions\Action::make('shop_products')
                    ->label(__('Belanja Bunga'))
                    ->url(ProductResource::getUrl())
                    ->button()
                    ->color('info')
                    ->size('lg')
                    ->icon('ri-flower-line'),
                Tables\Actions\Action::make('book_package')
                    ->label(__('Pesan Paket Dekorasi'))
                    ->url(PackageResource::getUrl())
                    ->button()
                    ->color('primary')
                    ->size('lg')
                    ->icon('ri-gift-line'),
            ])
            ->contentGrid([
                'default' => 1,
                'md' => 2,
                'lg' => 3,
                'xl' => 4,
            ])
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    // Top Section: Image
                    Tables\Columns\ImageColumn::make('item.image_url')
                        ->label('')
                        ->height('180px')
                        ->width('100%')
                        ->extraAttributes(['class' => 'w-full bg-gray-100 dark:bg-white/5 rounded-t-2xl overflow-hidden'])
                        ->extraImgAttributes([
                            'class' => 'w-full h-full object-cover transition-transform duration-700 group-hover:scale-110',
                            'style' => 'width: 100%; height: 180px; object-fit: cover;',
                        ]),

                    // Middle Section: Content
                    Tables\Columns\Layout\Stack::make([
                        // Item Type Badge
                        Tables\Columns\TextColumn::make('type_badge')
                            ->state(fn (?Cart $record) => $record?->product_id ? __('Produk') : __('Paket'))
                            ->badge()
                            ->color(fn (?Cart $record) => $record?->product_id ? 'info' : 'warning')
                            ->size('xs')
                            ->extraAttributes(['class' => 'mb-2 self-start']),

                        // Item Name
                        Tables\Columns\TextColumn::make('item.name')
                            ->label(__('Nama Item'))
                            ->weight('bold')
                            ->size('lg')
                            ->lineClamp(1)
                            ->color('gray')
                            ->extraAttributes(['class' => 'tracking-tight']),

                        // Quantity & Price Info (Now with Stock indicator)
                        Tables\Columns\Layout\Stack::make([
                            Tables\Columns\Layout\Split::make([
                                Tables\Columns\TextColumn::make('quantity')
                                    ->prefix(__('Jumlah').': ')
                                    ->weight('medium')
                                    ->size('sm')
                                    ->icon('heroicon-o-shopping-bag')
                                    ->extraAttributes(['class' => 'text-gray-500']),

                                Tables\Columns\TextColumn::make('product.stock')
                                    ->prefix(__('Stok: '))
                                    ->visible(fn (?Cart $record) => (bool) ($record?->product_id ?? false))
                                    ->color(fn (?Cart $record) => ($record?->product?->stock ?? 0) > 0 ? 'success' : 'danger')
                                    ->weight('bold')
                                    ->size('xs')
                                    ->alignEnd(),
                            ]),

                            Tables\Columns\TextColumn::make('subtotal')
                                ->money('IDR')
                                ->weight('black')
                                ->size('lg')
                                ->color('primary')
                                ->extraAttributes(['class' => 'mt-1']),
                        ])->extraAttributes(['class' => 'mt-4 pt-3 border-t border-gray-100 dark:border-white/5']),
                    ])->extraAttributes(['class' => 'p-4 flex-1 flex flex-col']),
                ])->extraAttributes([
                    'class' => 'bg-white dark:bg-gray-900 rounded-2xl shadow-sm hover:shadow-xl transition-all duration-500 overflow-hidden group border border-gray-100 dark:border-white/10 flex flex-col h-full',
                ]),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('checkout')
                        ->label(__('Beli Sekarang'))
                        ->button()
                        ->color('success')
                        ->size('md')
                        ->icon('heroicon-m-shopping-cart')
                        ->extraAttributes(['class' => 'flex-1 !rounded-xl shadow-lg shadow-success-500/20 font-bold'])
                        ->slideOver()
                        ->modalHeading(fn (Cart $record) => $record->product_id ? __('Checkout Produk') : __('Checkout Layanan'))
                        ->steps(fn (Cart $record) => $record->product_id
                            ? ProductResource::getCheckoutWizardSteps($record->product)
                            : PackageResource::getCheckoutWizardSteps($record->package)
                        )
                        ->action(function (Cart $record, array $data, Component $livewire) {
                            if ($record->product_id) {
                                $response = ProductResource::handleCheckout($record->product, $data, $livewire);
                            } else {
                                $response = PackageResource::handleCheckout($record->package, $data, $livewire);
                            }
                            $record->delete();
                            NativeNotificationHelper::success(__('Pesanan Anda sedang diproses!'));

                            return $response;
                        }),

                    Tables\Actions\DeleteAction::make()
                        ->label(__('Hapus Item'))
                        ->icon('heroicon-o-trash')
                        ->button()
                        ->color('danger')
                        ->outlined()
                        ->size('md')
                        ->extraAttributes(['class' => 'flex-1 !rounded-xl font-bold'])
                        ->after(fn () => NativeNotificationHelper::info(__('Dihapus'), __('Produk berhasil dihapus dari keranjang.'))),
                ])
                    ->dropdown(false)
                    ->extraAttributes([
                        'class' => 'flex gap-2 p-3 bg-gray-50 dark:bg-white/5 border-t border-gray-100 dark:border-white/5',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\Action::make('checkout_all')
                    ->label(__('Checkout Semua'))
                    ->color('primary')
                    ->icon('heroicon-m-credit-card')
                    ->button()
                    ->size('lg')
                    ->extraAttributes(['class' => 'shadow-xl shadow-primary-500/20 font-black'])
                    ->action(function () {
                        Notification::make()
                            ->title(__('Fitur Checkout Massal Segang Dikembangkan'))
                            ->info()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageCarts\ManageCarts::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', auth()->id());
    }
}
