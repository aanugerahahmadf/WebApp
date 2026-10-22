<?php

namespace App\Filament\User\Resources\ReviewResource;

use App\Filament\User\Resources\ReviewResource\Pages\ManageReviews\ManageReviews;

use App\Filament\User\Resources\CatalogResource\CatalogResource;
use App\Forms\Components\StarRating\StarRating;
use App\Helpers\NativeNotificationHelper\NativeNotificationHelper;
use App\Models\Catalog\Catalog;
use App\Models\Review\Review;
use App\Models\Order\Order;
use App\Support\AppIcon\AppIcon;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use emmanpbarrameda\FilamentTakePictureField\Forms\Components\TakePicture;
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

    protected static ?string $navigationIcon = AppIcon::REVIEW;

    protected static ?int $navigationSort = 4;

    public static function getGloballySearchableAttributes(): array
    {
        return ['package.name', 'catalog.name', 'comment'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->package?->name ?? $record->catalog?->name ?? __('Ulasan');
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
                    ->description(__('Silahkan pilih katalog yang ingin Anda beri ulasan.'))
                    ->schema([
                        Forms\Components\Select::make('catalog_id')
                            ->searchable()
                            ->relationship('catalog', 'name', fn ($query) => $query->whereHas('orders', fn ($q) => $q->where('user_id', Filament::auth()->id())))
                            ->preload()
                            ->label(__('Katalog'))
                            ->prefixIcon('/images/icons/bag.svg')
                            ->required(),
                    ]),
                Forms\Components\Section::make(__('Rating & Ceritakan Pengalaman Anda'))
                    ->schema([
                        Forms\Components\Placeholder::make('store_info')
                            ->label(__('Informasi Toko'))
                            ->content((string) config('app.name')),
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

    /** Fields for writing a review directly from a catalog detail page. */
    public static function itemReviewFields(string $itemField, int $itemId): array
    {
        if (! in_array($itemField, ['catalog_id'], true)) {
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
            Forms\Components\Repeater::make('photos')
                ->label(__('Foto Ulasan'))
                ->schema([
                    TakePicture::make('photo')
                        ->label(__('Foto'))
                        ->disk('public')
                        ->directory('review-photos')
                        ->visibility('public')
                        ->imageQuality(90)
                        ->aspect('3:4')
                        ->useModal(true)
                        ->showCameraSelector(true)
                        ->captureMaxDimensions(1280, 720)
                        ->autoStart(false)
                        ->helperText(__('Ambil foto dengan kamera, rekam video, atau pilih dari galeri/file.')),
                ])
                ->columns(1)
                // SATU baris foto selalu ada di halaman tulis ulasan.
                //
                // Dulu `defaultItems(0)`, jadi repeater-nya KOSONG dan field
                // TakePicture-nya tidak pernah dirender: tidak ada tombol
                // kamera, tidak ada galeri, tidak ada file.
                ->defaultItems(1)
                ->minItems(0)
                ->maxItems(10)
                ->addActionLabel(__('Tambah Foto'))
                ->itemNumbers(true)
                            // Nomor urutnya dibuat repeater sendiri (itemNumbers),
                            // bukan dihitung di sini.
                            //
                            // Versi lama: `array_search($state, $get('photos')) + 1`.
                            // Dua masalah: (1) `Get` TIDAK bisa di-inject ke
                            // closure label repeater -- yang tersedia cuma
                            // state/uuid/container; (2) kuncinya UUID, bukan
                            // angka, jadi `string + 1` meledak.
                            //
                            // Closure ini tadinya tidak pernah jalan karena
                            // repeater-nya selalu kosong.
                            ->itemLabel(fn (array $state): ?string => $state['photo'] ? __('Foto') : null)
                ->helperText(__('Opsional — tambah beberapa foto. Setiap foto bisa diambil dari kamera, video, galeri, atau file.')),
        ];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated(false)
            ->emptyStateHeading(__('Belum ada ulasan'))
            ->emptyStateDescription(__('Bagikan pengalamanmu dengan kami!'))
            ->emptyStateActions([
                // CUMA satu aksi: ke katalog. Dulu ada dua tombol
                // ("Belanja Bunga" dan "Pesan Paket Dekorasi") dan KEDUANYA
                // menuju ke URL yang sama persis -- jadi yang kedua cuma
                // tombol yang lebih besar, bukan pintu baru.
                Tables\Actions\Action::make('shop_catalogs')
                    ->label(__('Belanja Bunga'))
                    ->url(CatalogResource::getUrl())
                    ->button()
                    ->color('info')
                    ->size('lg')
                    ->icon('/images/icons/flower.svg'),
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
                            ->getStateUsing(fn ($record) => $record->package?->name ?? $record->catalog?->name ?? '-')
                            ->formatStateUsing(fn ($state) => __($state))
                            ->weight(FontWeight::Bold)
                            ->size('md')
                            ->icon(fn ($record) => $record->package_id ? '/images/icons/briefcase.svg' : '/images/icons/bag.svg')
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
                            ->icon('/images/icons/clock.svg'),
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
                    ->icon('/images/icons/filter.svg')
                    ->label(__('Filter'))
                    ->color(fn ($livewire) => count($livewire->getTable()->getFilterIndicators()) > 0 ? 'primary' : 'gray')
                    ->badge(fn ($livewire) => count($livewire->getTable()->getFilterIndicators()) > 0 ? count($livewire->getTable()->getFilterIndicators()) : null)
            )
            ->headerActions([
                // Ulasan ditulis di HALAMAN PENUH (CreateReviews), bukan
                // slideOver, dan bukan MODAL.
                //
                // Kenapa bukan modal: tombol ini sekarang tautan biasa ke
                // halaman CreateReviews (mode "pilih katalog", route
                // /flowerdecorationscatalog/reviews). User mendarat di
                // halaman penuh, memilih katalog, lalu FORM-nya yang terbuka
                // ada di halaman CreateReviews milik katalog itu.
                //
                // Kenapa tombolnya jadi halaman pilih katalog dulu: halaman
                // CreateReviews milik SATU katalog (route /{record}/reviews),
                // jadi dari daftar ulasan -- yang tidak punya satu katalog
                // tunggal -- harus ada langkah memilih katalog.
                //
                // Daftar pilihannya TIDAK dibatasi ke katalog yang sudah
                // dipesan: tombol yang tidak bisa dipakai sama sekali
                // (daftar kosong) lebih buruk daripada bolehnya mengulas
                // katalog yang belum dibeli -- halaman detail katalog memang
                // sudah menawarkan hal yang sama.
                Tables\Actions\Action::make('write_review')
                    ->label(__('Tulis Ulasan'))
                    ->button()
                    ->color('primary')
                    ->size('lg')
                    ->icon('/images/icons/pencil.svg')
                    // Tautan biasa ke halaman CreateReviews (mode "pilih
                    // katalog"), BUKAN action yang membuka modal.
                    ->url(fn (): string => CatalogResource::getUrl('write-review'))
                    ->visible(fn (): bool => static::reviewableCatalogOptions() !== []),
            ]);
    }

    /**
     * Katalog yang bisa diulas oleh user yang sedang login.
     *
     * Semua katalog aktif yang BELUM diulas user ini. Katalog yang sudah
     * diulas disembunyikan: CreateReviews akan memantulkan user dengan
     * "Anda sudah menulis ulasan untuk katalog ini", jadi menawarkan lagi
     * hanya membuang klik.
     *
     * Katalog yang SUDAH pernah dipesan user didahulukan -- itu kandidat
     * paling mungkin mau diulas -- tapi TIDAK jadi syarat: user yang belum
     * pernah memesan pun harus bisa menulis ulasan, lewat tombol yang sama.
     *
     * @return array<int|string, string>
     */
    public static function reviewableCatalogOptions(): array
    {
        $query = static::reviewableCatalogQuery();

        if ($query === null) {
            return [];
        }

        return static::orderReviewedFirst(
            $query->orderBy('name')->get(['id', 'name'])
        )
            ->mapWithKeys(fn (Catalog $catalog): array => [$catalog->id => $catalog->name])
            ->all();
    }

    /**
     * Katalog aktif yang BELUM diulas user yang sedang login -- versi MODEL.
     *
     * Dipakai halaman CreateReviews (mode "pilih katalog") untuk menampilkan
     * kartu katalog. Bedanya dengan `reviewableCatalogOptions()`: yang ini
     * memuat media (buat thumbnail) dan mengembalikan modelnya, sedangkan
     * yang itu cuma id => nama (dipakai untuk pengecekan "ada tidaknya katalog
     * yang bisa diulas" -- dipanggil tiap render, jadi harus ringan).
     *
     * @return Collection<int, Catalog>
     */
    public static function reviewableCatalogs(): Collection
    {
        $query = static::reviewableCatalogQuery();

        if ($query === null) {
            return collect();
        }

        return static::orderReviewedFirst(
            $query->with('media')->orderBy('name')->get()
        )->values();
    }

    /**
     * Query katalog aktif yang BELUM diulas user yang sedang login.
     *
     * Null kalau tidak ada user yang login (tamu) -- pemanggil wajib
     * mengembalikannya sebagai daftar kosong.
     */
    protected static function reviewableCatalogQuery(): ?Builder
    {
        $userId = Filament::auth()->id();

        if ($userId === null) {
            return null;
        }

        $reviewed = Review::query()
            ->where('user_id', $userId)
            ->pluck('catalog_id')
            ->all();

        return Catalog::query()
            ->where('is_active', true)
            ->when($reviewed !== [], fn (Builder $query) => $query->whereNotIn('id', $reviewed));
    }

    /**
     * Katalog yang SUDAH pernah dipesan user didahulukan.
     *
     * Urutannya: kandidat yang paling mungkin mau diulas di depan.
     */
    protected static function orderReviewedFirst(Collection $catalogs): Collection
    {
        $ordered = Order::query()
            ->where('user_id', Filament::auth()->id())
            ->pluck('catalog_id')
            ->all();

        return $catalogs->sortByDesc(fn (Catalog $catalog): bool => in_array($catalog->id, $ordered, true));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageReviews::route('/'),
        ];
    }
}
