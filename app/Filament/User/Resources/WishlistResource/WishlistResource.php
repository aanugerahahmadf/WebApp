<?php

namespace App\Filament\User\Resources\WishlistResource;

use App\Filament\User\Resources\ProductResource\ProductResource;
use App\Filament\User\Resources\WishlistResource\Pages\ManageWishlists\ManageWishlists;
use App\Helpers\NativeNotificationHelper\NativeNotificationHelper;
use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Models\Wishlist\Wishlist;
use App\Providers\NativeServiceProvider\NativeServiceProvider;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

class WishlistResource extends Resource
{
    protected static ?string $model = Wishlist::class;

    protected static ?string $slug = 'wishlists';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    public static function getGloballySearchableAttributes(): array
    {
        return ['package.name', 'package.category.name', 'product.name', 'product.category.name'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->package?->name ?? $record->product?->name ?? __('Wishlist');
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            __('Jenis') => $record->package_id ? __('Paket') : __('Produk'),
            __('Kategori') => $record->package?->category?->name ?? $record->product?->category?->name ?? '-',
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

    public static function getNavigationLabel(): string
    {
        return __('Favorit Saya');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Favorit Saya');
    }

    public static function getModelLabel(): string
    {
        return __('Favorit Saya');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', Filament::auth()->id());
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Favorit Baru'))
                    ->description(__('Pilih paket yang ingin disimpan ke daftar wishlist Anda.'))
                    ->icon('heroicon-o-heart')
                    ->schema([
                        Forms\Components\Select::make('package_id')
                            ->searchable()
                            ->relationship('package', 'name')
                            ->required()
                            ->preload()
                            ->live()
                            ->prefixIcon('heroicon-o-gift')
                            ->label(__('Pilih Paket')),

                        Forms\Components\Placeholder::make('package_preview')
                            ->hiddenLabel()
                            ->content(function (Forms\Get $get) {
                                $packageId = $get('package_id');
                                if (! $packageId) {
                                    return null;
                                }

                                $package = Package::with('category')->find($packageId);
                                if (! $package) {
                                    return null;
                                }

                                $imageUrl = $package->image_url;
                                $imageHtml = $imageUrl
                                    ? '<img src="'.$imageUrl.'" style="height: 15rem; width: 100%; object-fit: cover;" class="rounded-t-2xl">'
                                    : '<div style="height: 15rem; width: 100%;" class="bg-gray-100 dark:bg-gray-800 rounded-t-2xl flex products-center justify-center"><svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg></div>';

                                $categoryName = $package->category ? $package->category->name : 'Uncategorized';
                                $price = 'IDR '.number_format((float) $package->price, 2, '.', ',');

                                return new HtmlString('
                                    <div class="mt-4 bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden w-full max-w-sm mx-auto flex flex-col products-center">
                                        '.$imageHtml.'
                                        <div class="p-6 flex flex-col products-center justify-center space-y-3 w-full text-center">
                                            <h3 class="font-bold text-lg text-gray-950 dark:text-white leading-tight">'.e(__($package->name)).'</h3>
                                            <span class="inline-flex products-center justify-center px-2 py-0.5 rounded-md text-sm font-medium ring-1 ring-inset ring-amber-600/20 text-amber-600 bg-amber-50 dark:ring-amber-500/30 dark:text-amber-500 dark:bg-amber-500/10">
                                                '.e(__($categoryName)).'
                                            </span>
                                            <p class="font-bold text-md text-amber-600 dark:text-amber-500">'.$price.'</p>
                                        </div>
                                    </div>
                                ');
                            })
                            ->visible(fn (Forms\Get $get) => filled($get('package_id')))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll(NativeServiceProvider::isNativeMobile() ? null : '30s')
            ->emptyStateHeading(__('Belum ada favorit'))
            ->emptyStateDescription(__('Temukan produk atau layanan impian Anda dan simpan di sini.'))
            ->emptyStateIcon('heroicon-o-heart')
            ->emptyStateActions([
                Tables\Actions\Action::make('explore')
                    ->label(__('Cari Produk & Layanan'))
                    ->url(ProductResource::getUrl())
                    ->button()
                    ->color('rose')
                    ->size('lg'),
            ])
            ->contentGrid([
                'default' => 2,
                'md' => 4,
                'lg' => 5,
                'xl' => 6,
            ])
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    // Image with absolute discount badge placeholder logic
                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\ImageColumn::make('item_image')
                            ->state(fn ($record) => $record ? ($record->product_id ? $record->product?->image_url : $record->package?->image_url) : null)
                            ->label('')
                            ->height('170px')
                            ->width('100%')
                            ->extraAttributes(['class' => 'w-full flex justify-center items-center bg-white p-4 rounded-t-xl overflow-hidden'])
                            ->extraImgAttributes([
                                'class' => 'object-contain transition-all duration-500 group-hover:scale-110 !mx-auto',
                                'style' => 'max-height: 100%; width: auto;',
                            ]),

                        Tables\Columns\TextColumn::make('discount_pct')
                            ->state(function ($record) {
                                if (! $record) {
                                    return null;
                                }
                                $item = $record->product ?? $record->package;
                                if (! $item || $item->discount_price <= 0 || $item->price <= 0) {
                                    return null;
                                }

                                return '-'.round((($item->price - $item->discount_price) / $item->price) * 100).'%';
                            })
                            ->extraAttributes([
                                'class' => 'absolute top-2 right-2 font-black px-2 py-1 rounded shadow-lg z-10 transform scale-100 group-hover/img-overlay:scale-110 transition-transform duration-300',
                                'style' => 'background-color: #dc2626 !important; color: #ffffff !important; width: fit-content; font-size: 0.8rem; line-height: 1; pointer-events: none; visibility: visible !important;',
                            ])
                            ->visible(fn ($record) => $record && (($record->product?->discount_price > 0) || ($record->package?->discount_price > 0))),
                    ])->extraAttributes(['class' => 'relative overflow-hidden group/img-overlay']),

                    Tables\Columns\Layout\Stack::make([
                        // Category & Name
                        Tables\Columns\TextColumn::make('item_category')
                            ->state(fn ($record) => $record ? ($record->product_id ? $record->product?->category?->name : $record->package?->category?->name) : null)
                            ->formatStateUsing(fn ($state) => __($state))
                            ->badge()
                            ->size('xs')
                            ->extraAttributes(['class' => 'mt-1 mb-1']),
                        Tables\Columns\TextColumn::make('item_name')
                            ->state(fn ($record) => $record ? ($record->product_id ? $record->product?->name : $record->package?->name) : null)
                            ->formatStateUsing(fn ($state) => __($state))
                            ->weight('bold')
                            ->size('xs')
                            ->lineClamp(2),
                        // Price Row
                        Tables\Columns\Layout\Stack::make([
                            Tables\Columns\TextColumn::make('price_display')
                                ->state(function ($record) {
                                    if (! $record) {
                                        return null;
                                    }
                                    $item = $record->product ?? $record->package;

                                    return $item?->discount_price > 0 ? $item->discount_price : $item?->price;
                                })
                                ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 0, ',', '.'))
                                ->weight('bold')
                                ->color('primary')
                                ->size('xs'),

                            Tables\Columns\TextColumn::make('original_price')
                                ->state(function ($record) {
                                    if (! $record) {
                                        return null;
                                    }
                                    $item = $record->product ?? $record->package;

                                    return $item?->discount_price > 0 ? $item->price : null;
                                })
                                ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 0, ',', '.'))
                                ->size('xs')
                                ->color('gray')
                                ->extraAttributes(['class' => 'line-through opacity-70'])
                                ->visible(fn ($record) => $record && (($record->product?->discount_price > 0) || ($record->package?->discount_price > 0))),
                        ])->space(0),

                        // Stats Footer
                        Tables\Columns\Layout\Split::make([
                            Tables\Columns\TextColumn::make('avg_rating')
                                ->state(function ($record) {
                                    if (! $record) {
                                        return null;
                                    }
                                    $item = $record->product ?? $record->package;
                                    if ($item instanceof Product) {
                                        return null;
                                    }

                                    return $item ? number_format($item->reviews()->avg('rating') ?: 0, 1) : null;
                                })
                                ->icon('heroicon-m-star')
                                ->iconColor('warning')
                                ->size('xs')
                                ->color('gray'),

                            Tables\Columns\TextColumn::make('sold_count')
                                ->state(function ($record) {
                                    if (! $record) {
                                        return null;
                                    }
                                    $item = $record->product ?? $record->package;

                                    return $item ? $item->orders()->count().' '.__('Terjual') : null;
                                })
                                ->size('xs')
                                ->color('gray')
                                ->alignEnd(),
                        ])->extraAttributes(['class' => 'pt-2 mt-auto']),

                    ])->space(1)->extraAttributes(['class' => 'p-2.5 flex-1 flex flex-col']),
                ])->extraAttributes([
                    'class' => 'bg-white dark:bg-gray-900 rounded-xl shadow-sm hover:shadow-xl hover:ring-1 hover:ring-primary-500/30 transition-all duration-300 group border border-transparent dark:border-white/10 flex flex-col overflow-hidden',
                ]),
            ])
            ->actions([
                Tables\Actions\DeleteAction::make()
                    ->label(__('Hapus'))
                    ->icon('heroicon-o-trash')
                    ->button()
                    ->color('danger')
                    ->size('sm')
                    ->extraAttributes(['class' => 'flex-1 justify-center rounded-lg shadow-sm font-bold'])
                    ->after(fn () => NativeNotificationHelper::info(__('Dihapus'), __('Produk berhasil dihapus dari favorit.'))),
            ])
            ->actionsAlignment('center')
            ->extraAttributes([
                'class' => 'filament-table-actions-container !flex !flex-row !gap-1 !p-3 !bg-gray-50/50 dark:!bg-white/5 !border-t dark:!border-gray-800',
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->slideOver()
                    ->label(__('Tambah Favorit'))
                    ->button()
                    ->size('lg')
                    ->color('primary')
                    ->icon('heroicon-m-plus-circle')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = Filament::auth()->id();

                        return $data;
                    })
                    ->after(fn () => NativeNotificationHelper::success(__('Berhasil ditambahkan ke favorit.'))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWishlists::route('/'),
        ];
    }
}
