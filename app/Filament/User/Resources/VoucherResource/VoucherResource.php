<?php

namespace App\Filament\User\Resources\VoucherResource;
use App\Filament\User\Resources\CatalogResource\CatalogResource;

use App\Enums\DiscountType\DiscountType;
use App\Filament\User\Pages\MessagesPage\MessagesPage;

use App\Filament\User\Resources\VoucherResource\Pages\ManageVouchers\ManageVouchers;
use App\Models\Voucher\Voucher;
use App\Support\AppIcon\AppIcon;
use Carbon\Carbon;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class VoucherResource extends Resource
{
    protected static ?string $model = Voucher::class;

    protected static ?string $slug = 'vouchers';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationIcon = AppIcon::VOUCHER;

    protected static bool $shouldRegisterNavigation = false;

    public static function getGloballySearchableAttributes(): array
    {
        // HANYA kolom asli tabel `vouchers`.
        //
        // `discount_amount` dan `min_purchase` adalah accessor Eloquent yang
        // dihitung dari relasi `discount`, bukan kolom database. Kalau ikut
        // dimasukkan ke daftar ini, query pencarian global memakai kolom yang
        // tidak ada (`no such column: vouchers.discount_amount`) dan SELURUH
        // pencarian global gagal -- bukan hanya hasil voucher.
        return ['code', 'description'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->code.' - '.__('Voucher Promo');
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        $discount = $record->discount_type === DiscountType::PERCENTAGE->value
            ? number_format($record->discount_amount, 0).'%'
            : 'Rp '.number_format($record->discount_amount, 0, ',', '.');

        return [
            __('Potongan') => $discount,
            __('Min. Blj') => 'Rp '.number_format($record->min_purchase, 0, ',', '.'),
            __('Berlaku') => $record->expires_at ? Carbon::parse($record->expires_at)->translatedFormat('d M Y') : __('Selamanya'),
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('index');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Belanja & Jelajahi');
    }

    public static function getNavigationLabel(): string
    {
        return __('Voucher Promo');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Voucher Promo');
    }

    public static function getModelLabel(): string
    {
        return __('Voucher Promo');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return static::getNavigationLabel();
    }

    public static function getEloquentQuery(): Builder
    {
        $userId = Filament::auth()->id();

        return parent::getEloquentQuery()
            ->with(['users' => fn ($q) => $q->where('users.id', $userId)])
            ->where('is_active', true)
            ->where(function (Builder $q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->where(function (Builder $q) use ($userId) {
                $q->where('is_global', true)
                    ->orWhereHas('users', fn (Builder $u) => $u->where('users.id', $userId));
            })
            ->whereDoesntHave('users', function (Builder $q) use ($userId) {
                $q->where('users.id', $userId)->whereNotNull('user_vouchers.used_at');
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('Belum ada promo baru'))
            ->emptyStateDescription(__('Voucher spesial dari kami akan otomatis muncul di sini. Coba tanyakan admin untuk promo menarik!'))
            ->emptyStateIcon('/images/icons/ticket.svg')
            ->emptyStateActions([
                Tables\Actions\Action::make('chat_admin')
                    ->label(__('Tanya Admin'))
                    ->url(MessagesPage::getUrl())
                    ->button()
                    ->color('primary')
                    ->size('lg')
                    ->icon('/images/icons/chat_dots.svg'),
            ])
            ->contentGrid([
                'default' => 2,
                'md' => 2,
                'lg' => 3,
                'xl' => 4,
            ])
            ->columns([
                Tables\Columns\Layout\Stack::make([

                    // -- NOMINAL BESAR DI TENGAH --
                    Tables\Columns\TextColumn::make('discount_amount')
                        ->formatStateUsing(function ($state, Voucher $record) {
                            if ($record->discount_type === DiscountType::PERCENTAGE->value) {
                                return number_format((float) $state, 0).'%';
                            }

                            return 'Rp '.number_format((float) $state, 2, ',', '.');
                        })
                        ->weight(FontWeight::Bold)
                        ->color('warning')
                        ->alignCenter()
                        ->extraAttributes(['class' => '!text-5xl mt-2 tracking-tight']),

                    // -- JENIS VOUCHER & MIN BELANJA DI TENGAH --
                    Tables\Columns\TextColumn::make('type_label')
                        ->state(fn (Voucher $record) => $record->discount_type === DiscountType::PERCENTAGE->value ? __('VOUCHER DISKON') : __('VOUCHER CASHBACK'))
                        ->weight(FontWeight::Bold)
                        ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall)
                        ->color('primary')
                        ->alignCenter()
                        ->extraAttributes(['class' => 'tracking-widest opacity-80 mt-2']),

                    Tables\Columns\TextColumn::make('min_purchase')
                        ->formatStateUsing(fn ($state) => $state > 0 ? __('Min. Blj').' Rp'.number_format((float) $state, 2, ',', '.') : __('Tanpa Minimum Belanja'))
                        ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall)
                        ->color('gray')
                        ->alignCenter(),

                    // -- GARIS PUTUS-PUTUS (DIVIDER NATIVE) --
                    Tables\Columns\TextColumn::make('divider')
                        ->default('')
                        ->extraAttributes(['class' => 'border-t-2 border-dashed border-gray-200 dark:border-gray-800 my-4 h-0 w-full pointer-events-none']),

                    // -- DESKRIPSI DAN TANGGAL --
                    Tables\Columns\TextColumn::make('description')
                        ->formatStateUsing(fn ($state) => __($state))
                        ->weight(FontWeight::Bold)
                        ->size(Tables\Columns\TextColumn\TextColumnSize::Medium)
                        ->color('gray')
                        ->alignCenter()
                        ->searchable()
                        ->extraAttributes(['class' => 'text-center mb-1 text-gray-900 dark:text-gray-100']),

                    Tables\Columns\TextColumn::make('expires_at')
                        ->formatStateUsing(fn ($state) => $state ? __('Berlaku s/d').' '.Carbon::parse($state)->translatedFormat('d M Y') : __('Berlaku Selamanya'))
                        ->size(Tables\Columns\TextColumn\TextColumnSize::ExtraSmall)
                        ->color(fn ($state) => $state && Carbon::parse($state)->diffInDays(now()) <= 3 ? 'danger' : 'gray')
                        ->icon('/images/icons/clock.svg')
                        ->alignCenter(),

                    // -- KODE VOUCHER (KOTAK BERWARNA DI TENGAH) --
                    Tables\Columns\TextColumn::make('code')
                        ->weight(FontWeight::Bold)
                        ->size(Tables\Columns\TextColumn\TextColumnSize::Small)
                        ->color('warning')
                        ->copyable()
                        ->copyMessage(__('Kode Disalin!'))
                        ->icon('/images/icons/clipboard.svg')
                        ->alignCenter()
                        ->searchable()
                        ->extraAttributes([
                            'class' => 'mt-4 bg-warning-50 flex dark:bg-warning-950/40 text-warning-600 dark:text-warning-400 px-4 py-2 rounded-xl border border-warning-200 dark:border-warning-800/60 justify-center items-center w-full mx-auto transition hover:bg-warning-100 dark:hover:bg-warning-900/60 cursor-pointer',
                        ]),

                ])->space(0),
            ])
            ->actions([
                Tables\Actions\Action::make('klaim')
                    ->label(__('Klaim Voucher'))
                    ->icon('/images/icons/plus_circle.svg')
                    ->color('primary')
                    ->button()
                    ->size('sm')
                    ->visible(fn ($record) => ! $record->users->contains(Filament::auth()->id()))
                    ->extraAttributes([
                        'class' => 'flex-1 justify-center rounded-lg shadow-sm font-bold',
                    ])
                    ->action(function ($record) {
                        if (! $record->users->contains(Filament::auth()->id())) {
                            $record->users()->attach(Filament::auth()->id(), [
                                'claimed_at' => now(),
                                'created_at' => now(),
                                'updated_at' => now(),
                            ]);
                            Notification::make()
                                ->title(__('Voucher Berhasil Diklaim!'))
                                ->body(__('Kini Anda bisa menggunakan voucher ini pada saat Checkout.'))
                                ->icon('/images/icons/check_circle.svg')
                                ->iconColor('success')
                                ->success()
                                ->send();
                        }
                    }),

                Tables\Actions\Action::make('pakai')
                    ->label(__('Gunakan'))
                    ->icon('/images/icons/bag.svg')
                    ->color('warning')
                    ->button()
                    ->size('sm')
                    ->visible(fn ($record) => $record->users->contains(Filament::auth()->id()))
                    ->url(fn () => CatalogResource::getUrl('index'))
                    ->extraAttributes([
                        'class' => 'flex-1 justify-center rounded-lg shadow-sm font-bold',
                    ]),
            ])
            ->actionsAlignment('center')
            ->extraAttributes([
                'class' => 'filament-table-actions-container !flex !flex-row !gap-1 !p-3 !bg-gray-50/50 dark:!bg-white/5 !border-t dark:!border-gray-800',
            ])
            ->paginated(false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageVouchers::route('/'),
        ];
    }
}
