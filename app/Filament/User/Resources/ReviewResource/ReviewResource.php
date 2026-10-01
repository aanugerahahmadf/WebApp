<?php

namespace App\Filament\User\Resources\ReviewResource;

use App\Filament\User\Resources\ReviewResource\Pages\ManageReviews\ManageReviews;
use App\Filament\User\Resources\PackageResource\PackageResource;
use App\Filament\User\Resources\ProductResource\ProductResource;
use App\Forms\Components\StarRating\StarRating;
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
use Illuminate\Support\Collection;
use Illuminate\Support\HtmlString;
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
            __('Rating') => new HtmlString(self::ratingStarsHtml((int) ($record->rating ?? 0))),
            __('Komentar') => Str::limit($record->comment ?? '-', 50),
        ];
    }

    public static function ratingStarsHtml(int $rating, string $starClass = 'h-5 w-5'): string
    {
        $rating = max(0, min(5, $rating));
        $star = '<svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" class="'.$starClass.'"><path fill-rule="evenodd" d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.006 5.404.434c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.434 2.082-5.005Z" clip-rule="evenodd"/></svg>';
        $stars = '';

        for ($index = 1; $index <= 5; $index++) {
            $color = $index <= $rating ? 'text-amber-400' : 'text-gray-300 dark:text-gray-600';
            $stars .= '<span class="'.$color.'">'.$star.'</span>';
        }

        return '<span class="inline-flex items-center gap-1" role="img" aria-label="Rating">'.$stars.'</span>';
    }

    public static function summaryData(Model $item, string $heading): array
    {
        $ratingCounts = $item->reviews()->selectRaw('rating, COUNT(*) as aggregate')
            ->groupBy('rating')
            ->pluck('aggregate', 'rating');

        return [
            'heading' => $heading,
            'total' => $item->reviews()->count(),
            'average' => round((float) $item->reviews()->avg('rating'), 1),
            'ratingCounts' => $ratingCounts,
            'commentCount' => $item->reviews()->whereNotNull('comment')->where('comment', '<>', '')->count(),
            'mediaCount' => $item->reviews()->where(function (Builder $query): void {
                $query->where(fn (Builder $photoQuery) => $photoQuery->whereNotNull('photo')->where('photo', '<>', ''))
                    ->orWhereJsonLength('photos', '>', 0);
            })->count(),
            'activeFilter' => self::activeReviewFilter(),
        ];
    }

    public static function activeReviewFilter(): string
    {
        $filter = (string) request()->query('review_filter', 'all');

        return in_array($filter, ['all', 'rating_1', 'rating_2', 'rating_3', 'rating_4', 'rating_5', 'comment', 'media'], true)
            ? $filter
            : 'all';
    }

    public static function filteredReviews(Model $item): Collection
    {
        return $item->reviews()->with('user')->latest()->get();
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
                        StarRating::make('rating')
                            ->label(__('Berikan Rating Bintang')),
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
            StarRating::make('rating')
                ->label(__('Berikan Rating Bintang'))
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

    /** Fields for writing a review directly from a product or package detail page. */
    public static function itemReviewFields(string $itemField, int $itemId): array
    {
        if (! in_array($itemField, ['package_id', 'product_id'], true)) {
            throw new \InvalidArgumentException('Unsupported review item field.');
        }

        return [
            Forms\Components\Hidden::make($itemField)->default($itemId),
            StarRating::make('rating')
                ->label(__('Berikan Rating Bintang')),
            Forms\Components\TextInput::make('title')
                ->label(__('Judul Ulasan'))
                ->maxLength(255),
            Forms\Components\Textarea::make('comment')
                ->label(__('Komentar Anda'))
                ->required()
                ->rows(5),
            Forms\Components\FileUpload::make('photo')
                ->label(__('Foto Ulasan'))
                ->image()
                ->directory('review-photos')
                ->disk('public')
                ->visibility('public')
                ->maxSize(5120)
                ->helperText(__('Opsional — unggah foto hasil dekorasi Anda.')),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated(false)
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
                            ->formatStateUsing(fn ($state): HtmlString => new HtmlString(self::ratingStarsHtml((int) $state)))
                            ->html()
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
                        // Ikon ★☆ murni hardcoded — bukan teks, tanpa angka, jangan dimasukkan ke language.
                        5 => '★★★★★',
                        4 => '★★★★☆',
                        3 => '★★★☆☆',
                        2 => '★★☆☆☆',
                        1 => '★☆☆☆☆',
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
