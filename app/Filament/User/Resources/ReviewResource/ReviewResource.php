<?php

namespace App\Filament\User\Resources\ReviewResource;

use App\Filament\User\Resources\ReviewResource\Pages\ManageReviews\ManageReviews;
use App\Filament\User\Resources\PackageResource\PackageResource;
use App\Filament\User\Resources\ProductResource\ProductResource;
use App\Helpers\NativeNotificationHelper\NativeNotificationHelper;
use App\Models\Review\Review;
use App\Models\Order\Order;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $slug = 'reviews';

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?int $navigationSort = 4;

    public static function getGloballySearchableAttributes(): array
    {
        return ['package.name', 'product.name', 'comment'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->package?->name ?? $record->product?->name ?? __('Ulasan');
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            __('Rating') => ($record->rating ?? 0).' ⭐',
            __('Komentar') => Str::limit($record->comment ?? '-', 50),
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
        return __('Ulasan Saya');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Ulasan Saya');
    }

    public static function getModelLabel(): string
    {
        return __('Ulasan Saya');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', Filament::auth()->id());
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Pilih Layanan'))
                    ->description(__('Silahkan pilih paket atau produk yang ingin Anda beri ulasan.'))
                    ->schema([
                        Forms\Components\Select::make('package_id')
                            ->searchable()
                            ->relationship('package', 'name', fn ($query) => $query->whereHas('orders', fn ($q) => $q->where('user_id', Filament::auth()->id())))
                            ->preload()
                            ->label(__('Layanan Paket'))
                            ->prefixIcon('heroicon-o-gift')
                            ->requiredWithout('product_id'),

                        Forms\Components\Select::make('product_id')
                            ->searchable()
                            ->relationship('product', 'name', fn ($query) => $query->whereHas('orders', fn ($q) => $q->where('user_id', Filament::auth()->id())))
                            ->preload()
                            ->label(__('Produk'))
                            ->prefixIcon('heroicon-o-shopping-bag')
                            ->requiredWithout('package_id'),
                    ])->columns(2),
                Forms\Components\Section::make(__('Rating & Ceritakan Pengalaman Anda'))
                    ->schema([
                        Forms\Components\Placeholder::make('organizer_info')
                            ->label(__('Informasi Studio'))
                            ->content(__('Wedding Flowers Decorasi Devi')),
                        Forms\Components\Select::make('rating')
                            ->searchable()
                            ->label(__('Berikan Rating Bintang'))
                            ->options([
                                5 => __('5 Bintang').' ('.__('Sangat Puas').')',
                                4 => __('4 Bintang').' ('.__('Puas').')',
                                3 => __('3 Bintang').' ('.__('Cukup').')',
                                2 => __('2 Bintang').' ('.__('Kurang').')',
                                1 => __('1 Bintang').' ('.__('Sangat Kurang').')',
                            ])
                            ->required()

                            ->native(false)
                            ->prefixIcon('heroicon-o-star')
                            ->extraAttributes(['class' => 'text-warning-600 font-bold']),
                        Forms\Components\TextInput::make('title')
                            ->label(__('Judul Ulasan'))
                            ->maxLength(255)
                            ->placeholder(__('Contoh: Sangat memuaskan dan rapi!'))
                            ->columnSpanFull(),
                        Forms\Components\Textarea::make('comment')
                            ->label(__('Komentar Anda'))
                            ->required()
                            ->rows(5)
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('photo')
                            ->label(__('Foto Ulasan'))
                            ->image()
                            ->directory('review-photos')
                            ->disk('public')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->helperText(__('Opsional — unggah foto hasil dekorasi Anda.'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Form fields untuk aksi "Tulis Ulasan" dari detail pesanan (paket/produk sudah terkunci).
     */
    public static function orderReviewFields(Order $order): array
    {
        return [
            Forms\Components\Hidden::make('package_id')->default($order->package_id),
            Forms\Components\Hidden::make('product_id')->default($order->product_id),
            Forms\Components\Placeholder::make('item_name')
                ->label(__('Layanan'))
                ->content(fn () => $order->package?->name ?? $order->product?->name ?? '-'),
            Forms\Components\Select::make('rating')
                ->searchable()
                ->label(__('Berikan Rating Bintang'))
                ->options([
                    5 => __('5 Bintang').' ('.__('Sangat Puas').')',
                    4 => __('4 Bintang').' ('.__('Puas').')',
                    3 => __('3 Bintang').' ('.__('Cukup').')',
                    2 => __('2 Bintang').' ('.__('Kurang').')',
                    1 => __('1 Bintang').' ('.__('Sangat Kurang').')',
                ])
                ->required()
                ->native(false)
                ->prefixIcon('heroicon-o-star')
                ->extraAttributes(['class' => 'text-warning-600 font-bold'])
                ->columnSpanFull(),
            Forms\Components\TextInput::make('title')
                ->label(__('Judul Ulasan'))
                ->maxLength(255)
                ->columnSpanFull(),
            Forms\Components\Textarea::make('comment')
                ->label(__('Komentar Anda'))
                ->required()
                ->rows(4)
                ->columnSpanFull(),
            Forms\Components\FileUpload::make('photo')
                ->label(__('Foto Ulasan'))
                ->image()
                ->directory('review-photos')
                ->disk('public')
                ->visibility('public')
                ->maxSize(5120)
                ->helperText(__('Opsional — unggah foto hasil dekorasi Anda.'))
                ->columnSpanFull(),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('Belum ada ulasan'))
            ->emptyStateDescription(__('Bagikan pengalamanmu dengan kami!'))
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
                'default' => 2,
                'md' => 2,
                'lg' => 3,
                'xl' => 4,
            ])
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    // Header (Package info & Rating)
                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\TextColumn::make('item_name')
                            ->getStateUsing(fn ($record) => $record->package?->name ?? $record->product?->name ?? '-')
                            ->formatStateUsing(fn ($state) => __($state))
                            ->weight(FontWeight::Bold)
                            ->size('md')
                            ->icon(fn ($record) => $record->package_id ? 'heroicon-s-briefcase' : 'heroicon-s-shopping-bag')
                            ->color('gray')
                            ->grow(false),
                        Tables\Columns\TextColumn::make('rating')
                            ->badge()
                            ->icon('heroicon-m-star')
                            ->color('warning')
                            ->alignEnd(),
                    ])->extraAttributes(['class' => 'mb-2 border-b border-gray-100 dark:border-gray-800 pb-2']),

                    // Middle Box (The Review Content)
                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\ImageColumn::make('photo')
                            ->label('')
                            ->state(fn ($record) => $record->photo_url)
                            ->height('100%')
                            ->width('100%')
                            ->square()
                            ->visible(fn ($record) => ! empty($record->photo_url)),
                        Tables\Columns\TextColumn::make('comment')
                            ->formatStateUsing(fn ($state) => __($state))
                            ->size('sm'),

                    ])->extraAttributes(['class' => 'bg-gray-50 dark:bg-gray-900 rounded-xl p-3']),

                    // Footer (Date & Meta)
                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\TextColumn::make('created_at')
                            ->date('d M Y, H:i')
                            ->size('xs')
                            ->color('gray')
                            ->icon('heroicon-o-clock'),
                    ])->extraAttributes(['class' => 'mt-2 pt-2']),

                ])->space(3)->extraAttributes(['class' => 'p-4 bg-white dark:bg-gray-950 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800']),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->label(__('Ubah'))
                    ->button()
                    ->color('warning')
                    ->size('sm')
                    ->extraAttributes(['class' => 'flex-1 justify-center rounded-lg shadow-sm font-bold'])
                    ->slideOver()
                    ->after(fn () => NativeNotificationHelper::success(__('Ulasan berhasil diperbarui.'))),
                Tables\Actions\DeleteAction::make()
                    ->label(__('Hapus'))
                    ->button()
                    ->color('danger')
                    ->size('sm')
                    ->extraAttributes(['class' => 'flex-1 justify-center rounded-lg shadow-sm font-bold'])
                    ->after(fn () => NativeNotificationHelper::info(__('Dihapus'), __('Ulasan Anda telah dihapus.'))),
            ])
            ->actionsAlignment('center')
            ->extraAttributes([
                'class' => 'filament-table-actions-container !flex !flex-row !gap-1 !p-3 !bg-gray-50/50 dark:!bg-white/5 !border-t dark:!border-gray-800',
            ])
            ->filters([
                SelectFilter::make('rating')
                    ->searchable()
                    ->label(__('Rating'))
                    ->options([
                        '5' => '⭐⭐⭐⭐⭐ '.__('5 Bintang'),
                        '4' => '⭐⭐⭐⭐ '.__('4 Bintang'),
                        '3' => '⭐⭐⭐ '.__('3 Bintang'),
                        '2' => '⭐⭐ '.__('2 Bintang'),
                        '1' => '⭐ '.__('1 Bintang'),
                    ]),

                SelectFilter::make('sort_by')
                    ->searchable()
                    ->label(__('Urutkan'))
                    ->options([
                        'latest' => __('Terbaru'),
                        'oldest' => __('Terlama'),
                        'rating_desc' => __('Rating Tertinggi'),
                        'rating_asc' => __('Rating Terendah'),
                    ])
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        'latest' => $query->reorder('created_at', 'desc'),
                        'oldest' => $query->reorder('created_at', 'asc'),
                        'rating_desc' => $query->reorder('rating', 'desc'),
                        'rating_asc' => $query->reorder('rating', 'asc'),
                        default => $query,
                    }),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersTriggerAction(
                fn (Tables\Actions\Action $action) => $action
                    ->icon('heroicon-m-funnel')
                    ->label(__('Filter'))
                    ->color(fn ($livewire) => count($livewire->getTable()->getFilterIndicators()) > 0 ? 'primary' : 'gray')
                    ->badge(fn ($livewire) => count($livewire->getTable()->getFilterIndicators()) > 0 ? count($livewire->getTable()->getFilterIndicators()) : null)
            )
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label(__('Tulis Ulasan'))
                    ->button()
                    ->color('primary')
                    ->size('lg')
                    ->icon('heroicon-m-pencil-square')
                    ->slideOver()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = Filament::auth()->id();

                        return $data;
                    })
                    ->after(fn () => NativeNotificationHelper::success(__('Terima kasih atas ulasan Anda!'))),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReviews::route('/'),
        ];
    }
}
