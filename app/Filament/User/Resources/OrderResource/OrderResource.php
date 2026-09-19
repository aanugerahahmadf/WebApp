<?php

namespace App\Filament\User\Resources\OrderResource;

use App\Enums\DiscountType\DiscountType;
use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Enums\OrderStatus\OrderStatus;
use App\Filament\User\Resources\OrderResource\Pages\EditOrder\EditOrder;
use App\Filament\User\Resources\OrderResource\Pages\ManageOrders\ManageOrders;
use App\Filament\User\Resources\OrderResource\Pages\ViewOrder\ViewOrder;
use App\Filament\User\Resources\PackageResource\PackageResource;
use App\Filament\User\Resources\ProductResource\ProductResource;
use App\Filament\User\Resources\ReviewResource\ReviewResource;
use App\Helpers\NativeNotificationHelper\NativeNotificationHelper;
use App\Models\Order\Order;
use App\Models\PaymentMethod\PaymentMethod;
use App\Models\Review\Review;
use App\Models\Transaction\Transaction;
use App\Models\Voucher\Voucher;
use App\Providers\NativeServiceProvider\NativeServiceProvider;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Livewire\Component;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $slug = 'orders';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?int $navigationSort = 2;

    public static function getGloballySearchableAttributes(): array
    {
        return ['order_number', 'package.name', 'product.name', 'user.full_name', 'user.phone', 'notes', 'status', 'payment_status'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return '#'.$record->order_number.' - '.(__($record->package?->name) ?? __($record->product?->name) ?? __('Pesanan'));
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            __('Status') => $record->status?->getLabel() ?? '-',
            __('Pembayaran') => $record->payment_status?->getLabel() ?? '-',
            __('Total') => 'Rp '.number_format($record->total_price, 0, ',', '.'),
            __('Tanggal') => $record->booking_date ? Carbon::parse($record->booking_date)->translatedFormat('d M Y') : '-',
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('view', ['record' => $record]);
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
        return __('Pesanan Saya');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Pesanan Saya');
    }

    public static function getModelLabel(): string
    {
        return __('Pesanan Saya');
    }

    public static function form(Forms\Form $form): Forms\Form
    {
        return $form
            ->schema([
                Forms\Components\Wizard::make([
                    Forms\Components\Wizard\Step::make(__('Detail Acara'))
                        ->icon('heroicon-o-calendar-days')
                        ->schema([
                            Forms\Components\Section::make(__('Pilih Waktu & Kebutuhan'))
                                ->schema([
                                    Forms\Components\DatePicker::make('booking_date')
                                        ->label(__('Rencana Tanggal Acara'))
                                        ->required()
                                        ->native(false)
                                        ->minDate(now()->startOfDay())
                                        ->prefixIcon('heroicon-o-calendar-days')
                                        ->columnSpanFull(),
                                    Forms\Components\TimePicker::make('booking_time')
                                        ->label(__('Waktu Pelaksanaan'))
                                        ->required()
                                        ->native(false)
                                        ->prefixIcon('heroicon-o-clock')
                                        ->columnSpanFull(),
                                    Forms\Components\TextInput::make('quantity')
                                        ->label(__('Jumlah yang ingin dibeli'))
                                        ->numeric()
                                        ->required()
                                        ->default(1)
                                        ->minValue(1)
                                        ->columnSpanFull(),
                                    Forms\Components\Textarea::make('notes')
                                        ->label(__('Alamat Lokasi'))
                                        ->rows(4)
                                        ->required()
                                        ->columnSpanFull(),
                                ]),
                        ]),

                    Forms\Components\Wizard\Step::make(__('Info Kontak'))
                        ->icon('heroicon-o-user-circle')
                        ->schema([
                            Forms\Components\Section::make(__('Verifikasi Data Anda'))
                                ->schema([
                                    Forms\Components\TextInput::make('customer_name')
                                        ->label(__('Nama Lengkap'))
                                        ->default(fn ($record) => $record?->user?->name ?? auth()->user()?->name)
                                        ->dehydrated(false)
                                        ->required(),
                                    Forms\Components\TextInput::make('whatsapp')
                                        ->label(__('Nomor WhatsApp'))
                                        ->default(fn ($record) => $record?->user?->whatsapp ?? auth()->user()?->whatsapp)
                                        ->dehydrated(false)
                                        ->tel()
                                        ->required()
                                        ->helperText(__('Notifikasi pembayaran akan dikirim ke nomor ini.')),
                                ])->columns(2),
                        ]),

                    Forms\Components\Wizard\Step::make(__('Voucher & Diskon'))
                        ->icon('heroicon-o-ticket')
                        ->schema([
                            Forms\Components\Section::make(__('Pilih Voucher Anda'))
                                ->description(__('Gunakan voucher yang telah Anda klaim di menu Voucher.'))
                                ->icon('heroicon-o-ticket')
                                ->schema([
                                    Forms\Components\Select::make('voucher_id')
                                        ->searchable()
                                        ->label(__('Voucher Tersedia'))
                                        ->prefixIcon('heroicon-o-ticket')
                                        ->options(function ($record) {
                                            $user = auth()->user();
                                            if (! $user || ! $record) {
                                                return [];
                                            }
                                            $finalPrice = $record->total_price ?? 0;
                                            $vouchers = Voucher::query()
                                                ->where('is_active', true)
                                                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
                                                ->whereHas('users', fn ($q) => $q->where('users.id', $user->id)->whereNull('user_vouchers.used_at'))
                                                ->get()
                                                ->filter(fn ($v) => $v->isValidFor($finalPrice));

                                            return $vouchers->mapWithKeys(function ($v) {
                                                $amount = $v->discount_type === DiscountType::PERCENTAGE
                                                    ? number_format($v->discount_amount, 2, ',', '.').'%'
                                                    : 'Rp '.number_format($v->discount_amount, 2, ',', '.');

                                                return [$v->id => $v->code.__(' - Diskon ').$amount];
                                            });
                                        })
                                        ->preload()
                                        ->live()
                                        ->afterStateUpdated(function (Forms\Set $set, Forms\Get $get, ?string $state, $record) {
                                            if (! $state) {
                                                $set('voucher_discount', 0);
                                                $set('_voucher_info', null);

                                                return;
                                            }
                                            $finalPrice = $record?->total_price ?? 0;
                                            $voucher = Voucher::find($state);
                                            if ($voucher && $voucher->isValidFor($finalPrice)) {
                                                $discount = $voucher->calculateDiscount($finalPrice);
                                                $set('voucher_discount', $discount);
                                                $set('_voucher_info', 'valid:'.$voucher->id.':'.$discount.':'.$voucher->description);
                                            } else {
                                                $set('voucher_id', null);
                                                $set('voucher_discount', 0);
                                                $set('_voucher_info', 'invalid');
                                            }
                                        })
                                        ->hint(fn (Forms\Get $get) => match (true) {
                                            str_starts_with((string) $get('_voucher_info'), 'valid:') => __('Voucher Berhasil Dipasang!'),
                                            $get('_voucher_info') === 'invalid' => __('Voucher tidak valid'),
                                            default => null,
                                        })
                                        ->hintIcon(fn (Forms\Get $get) => match (true) {
                                            str_starts_with((string) $get('_voucher_info'), 'valid:') => 'heroicon-m-check-circle',
                                            $get('_voucher_info') === 'invalid' => 'heroicon-m-x-circle',
                                            default => null,
                                        })
                                        ->hintColor(fn (Forms\Get $get) => str_starts_with((string) $get('_voucher_info'), 'valid:') ? 'success' : 'danger')
                                        ->helperText(__('Hanya voucher yang memenuhi syarat minimum belanja yang akan muncul di sini. Jika kosong, silakan ke menu Voucher untuk Klaim.')),

                                    Forms\Components\Hidden::make('voucher_discount')->default(0),
                                    Forms\Components\Hidden::make('_voucher_info'),

                                    Forms\Components\Placeholder::make('_discount_preview')
                                        ->hiddenLabel()
                                        ->visible(fn (Forms\Get $get) => str_starts_with((string) $get('_voucher_info'), 'valid:'))
                                        ->content(function (Forms\Get $get, $record) {
                                            $finalPrice = $record?->total_price ?? 0;
                                            $discount = (float) $get('voucher_discount');
                                            $final = max(0, $finalPrice - $discount);

                                            return new HtmlString(
                                                '<div class="flex flex-col gap-2 p-4 bg-success-50 dark:bg-success-950 rounded-xl border border-success-200 dark:border-success-800">'.
                                                    '<div class="flex justify-between text-sm">'.
                                                        '<span class="text-gray-600 dark:text-gray-400">'.__('Harga').'</span>'.
                                                        '<span class="font-semibold">Rp '.number_format($finalPrice, 2, ',', '.').'</span>'.
                                                    '</div>'.
                                                    '<div class="flex justify-between text-sm text-success-600 dark:text-success-400">'.
                                                        '<span class="flex products-center gap-1"><svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 6v.75m0 3v.75m0 3v.75m0 3V18m-9-5.25h5.25M7.5 15h3M3.375 5.25c-.621 0-1.125.504-1.125 1.125v3.026a2.999 2.999 0 0 1 0 5.198v3.026c0 .621.504 1.125 1.125 1.125h17.25c.621 0 1.125-.504 1.125-1.125v-3.026a2.999 2.999 0 0 1 0-5.198V6.375c0-.621-.504-1.125-1.125-1.125H3.375Z" /></svg> '.__('Diskon Voucher').'</span>'.
                                                        '<span class="font-bold">- Rp '.number_format($discount, 2, ',', '.').'</span>'.
                                                    '</div>'.
                                                    '<div class="flex justify-between text-base font-bold border-t border-success-300 dark:border-success-700 pt-2">'.
                                                        '<span>'.__('Total Bayar').'</span>'.
                                                        '<span class="text-success-700 dark:text-success-300">Rp '.number_format(max(0, $final), 2, ',', '.').'</span>'.
                                                    '</div>'.
                                                '</div>'
                                            );
                                        }),
                                ]),
                        ]),

                    Forms\Components\Wizard\Step::make(__('Konfirmasi'))
                        ->icon('heroicon-o-check-badge')
                        ->schema([
                            Forms\Components\Section::make(__('Ringkasan Pembayaran'))
                                ->schema([
                                    Forms\Components\Placeholder::make('pkg_summary')
                                        ->label(__('Paket / Produk'))
                                        ->content(fn ($record) => $record?->package?->name ?? $record?->product?->name ?? '-'),
                                    Forms\Components\Placeholder::make('price_summary')
                                        ->label(__('Total Harga'))
                                        ->content(fn ($record) => 'Rp '.number_format($record?->total_price ?? 0, 0, ',', '.'))
                                        ->extraAttributes(['class' => 'text-primary-600 dark:text-primary-400 font-bold text-2xl']),
                                ]),
                        ]),
                ])
                    ->submitAction(new HtmlString(Blade::render(<<<'BLADE'
                    <x-filament::button
                        type="submit"
                        size="sm"
                    >
                        {{ __('Simpan Perubahan') }}
                    </x-filament::button>
                BLADE)))
                    ->columnSpanFull(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('user_id', Filament::auth()->id());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->poll(NativeServiceProvider::isNativeMobile() ? null : '30s')
            ->emptyStateHeading(__('Belum ada pesanan'))
            ->emptyStateDescription(__('Wujudkan acara impianmu dengan paket terbaik dari kami. Mulai pesan sekarang!'))
            ->emptyStateIcon('heroicon-o-shopping-bag')
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
                'sm' => 2,
                'md' => 2,
                'lg' => 3,
                'xl' => 4,
            ])
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    // Image Section with Status Overlay
                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\ImageColumn::make('item_image')
                            ->state(fn ($record) => $record->package?->image_url ?? $record->product?->image_url)
                            ->label('')
                            ->height('140px')
                            ->width('100%')
                            ->extraAttributes(['class' => 'w-full flex justify-center products-center bg-gray-50 dark:bg-gray-800 rounded-t-2xl overflow-hidden'])
                            ->extraImgAttributes([
                                'class' => 'aspect-video object-cover transition-all duration-500 group-hover:scale-110 !mx-auto',
                                'style' => 'height: 140px; width: 100%;',
                            ]),

                    ])->extraAttributes(['class' => 'relative overflow-hidden group/img-overlay']),

                    Tables\Columns\Layout\Stack::make([
                        // Category Badge
                        Tables\Columns\TextColumn::make('item_category')
                            ->state(fn ($record) => $record->package?->category?->name ?? $record->product?->category?->name)
                            ->formatStateUsing(fn ($state) => __($state))
                            ->badge()
                            ->color('warning')
                            ->size('xs')
                            ->alignCenter()
                            ->extraAttributes(['class' => 'mt-1 mb-1']),

                        // Package Name
                        Tables\Columns\TextColumn::make('item_name')
                            ->state(fn ($record) => $record->package?->name ?? $record->product?->name)
                            ->formatStateUsing(fn ($state) => __($state))
                            ->weight('bold')
                            ->size('xs')
                            ->lineClamp(1)
                            ->color('info')
                            ->alignCenter()
                            ->extraAttributes(['class' => 'mt-1']),
                        Tables\Columns\TextColumn::make('order_number')
                            ->prefix('#')
                            ->size('xs')
                            ->color('gray')
                            ->weight('medium')
                            ->alignCenter(),
                        Tables\Columns\Layout\Stack::make([
                            Tables\Columns\TextColumn::make('booking_date')
                                ->label(__('Tanggal:'))
                                ->date('d M Y')
                                ->icon('heroicon-m-calendar-days')
                                ->size('xs')
                                ->color('primary')
                                ->alignCenter(),
                            Tables\Columns\TextColumn::make('booking_time')
                                ->time('H:i')
                                ->icon('heroicon-m-clock')
                                ->size('xs')
                                ->color('info')
                                ->alignCenter(),
                            Tables\Columns\TextColumn::make('payment_status')
                                ->badge()
                                ->size('xs')
                                ->alignCenter(),
                            Tables\Columns\TextColumn::make('total_price')
                                ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 2, ',', '.'))
                                ->weight('black')
                                ->size('xs')
                                ->color('primary')
                                ->alignCenter(),
                        ])->space(2)->extraAttributes(['class' => 'mt-3']),
                        Tables\Columns\TextColumn::make('avg_rating')
                            ->state(fn ($record) => $record?->package ? number_format($record->package->reviews()->avg('rating') ?: 0, 1) : ($record?->product ? number_format($record->product->reviews()->avg('rating') ?: 0, 1) : '5.0'))
                            ->icon('heroicon-m-star')
                            ->iconColor('warning')
                            ->size('xs')
                            ->color('gray')
                            ->weight('bold')
                            ->alignCenter()
                            ->extraAttributes(['class' => 'pt-3 mt-auto']),
                    ])->space(1)->extraAttributes(['class' => 'p-2.5 flex-1 flex flex-col']),
                ])->extraAttributes([
                    'class' => 'bg-white dark:bg-gray-900 rounded-2xl shadow-sm hover:shadow-xl transition-all duration-300 overflow-hidden group border border-transparent dark:border-white/10 flex flex-col',
                ]),
            ])

            ->filters([
                SelectFilter::make('status')
                    ->searchable()
                    ->options(OrderStatus::class)
                    ->label(__('Status Pesanan')),
                Tables\Filters\Filter::make('id')
                    ->form([
                        Forms\Components\TextInput::make('value')
                            ->label(__('ID')),
                    ])
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn ($q, $id) => $q->where('id', $id)))
                    ->hidden(),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersTriggerAction(
                fn (Tables\Actions\Action $action) => $action
                    ->icon('heroicon-m-funnel')
                    ->label(__('Filter'))
                    ->color(fn ($livewire) => count($livewire->getTable()->getFilterIndicators()) > 0 ? 'primary' : 'gray')
                    ->badge(fn ($livewire) => count($livewire->getTable()->getFilterIndicators()) > 0 ? count($livewire->getTable()->getFilterIndicators()) : null)
            )
            ->actions([
                Tables\Actions\ActionGroup::make([

                    // Bayar (auto-confirm, no third-party gateway)
                    Tables\Actions\Action::make('pay_now')
                        ->label(__('Bayar Sekarang'))
                        ->icon('heroicon-m-credit-card')
                        ->color('success')
                        ->visible(fn ($record) => in_array($record?->payment_status, [
                            OrderPaymentStatus::UNPAID,
                            OrderPaymentStatus::FAILED,
                            OrderPaymentStatus::PENDING,
                        ]))
                        ->requiresConfirmation()
                        ->modalHeading(__('Konfirmasi Pembayaran'))
                        ->modalDescription(fn (Order $record) => __('Tandai pesanan #:order sebagai LUNAS?', ['order' => $record->order_number]))
                        ->action(function (Order $record) {
                            $transaction = $record->latestTransaction;
                            if ($transaction) {
                                $transaction->update([
                                    'status' => 'completed',
                                    'paid_at' => now(),
                                ]);
                            }
                            $record->update(['payment_status' => OrderPaymentStatus::PAID]);
                            Notification::make()->title(__('Pembayaran Berhasil'))->success()->send();
                        }),

                    // Detail
                    Tables\Actions\ViewAction::make()
                        ->label(__('Detail Pesanan'))
                        ->slideOver()
                        ->modalWidth('full'),

                    // Edit
                    Tables\Actions\EditAction::make()
                        ->label(__('Ubah Pesanan'))
                        ->slideOver()
                        ->modalWidth('full')
                        ->visible(fn ($record) => in_array($record?->status, [
                            OrderStatus::PENDING,
                            OrderStatus::CONFIRMED,
                            OrderStatus::COMPLETED,
                        ]))
                        ->after(fn () => NativeNotificationHelper::success(__('Pesanan berhasil diperbarui.'))),

                    // Batalkan
                    Tables\Actions\Action::make('cancel_order')
                        ->label(__('Batalkan Pesanan'))
                        ->icon('heroicon-m-x-circle')
                        ->color('danger')
                        ->modalHeading(__('Batalkan Pesanan?'))
                        ->modalDescription(__('Pesanan yang dibatalkan tidak dapat dikembalikan.'))
                        ->requiresConfirmation()
                        ->visible(fn ($record) => in_array($record?->status, [
                            OrderStatus::PENDING,
                            OrderStatus::CONFIRMED,
                            OrderStatus::COMPLETED,
                        ]))
                        ->action(function ($record) {
                            // Update status ke cancelled (trigger observer → notifikasi + payment_status)
                            $record->update(['status' => OrderStatus::CANCELLED]);

                            // 3. Hapus order dari tabel
                            $record->delete();
                        }),

                    // Preview & Download Invoice PDF
                    Tables\Actions\Action::make('preview_invoice')
                        ->label(__('Lihat Invoice'))
                        ->icon('heroicon-o-document-text')
                        ->color('gray')
                        ->modalHeading(fn (Order $record) => 'Invoice #'.$record->order_number)
                        ->modalContent(fn (Order $record) => new HtmlString(
                            '<div style="width:100%;height:75vh;">'
                            .'<iframe src="'.route('invoice.pdf', $record).'" '
                            .'style="width:100%;height:100%;border:none;border-radius:4px;" '
                            .'title="Invoice #'.$record->order_number.'">'
                            .'</iframe>'
                            .'</div>'
                        ))
                        ->modalFooterActions(fn (Order $record) => [
                            Action::make('download_pdf')
                                ->label(__('Download PDF'))
                                ->icon('heroicon-o-arrow-down-tray')
                                ->color('primary')
                                ->url(route('invoice.pdf', ['order' => $record, 'download' => 1]))
                                ->openUrlInNewTab(),
                        ])
                        ->modalWidth('4xl')
                        ->slideOver(false),

                    // Tulis Ulasan (hanya order selesai)
                    Tables\Actions\Action::make('write_review')
                        ->label(__('Tulis Ulasan'))
                        ->icon('heroicon-m-star')
                        ->color('warning')
                        ->visible(fn ($record) => $record->status === OrderStatus::COMPLETED)
                        ->modalHeading(__('Tulis Ulasan'))
                        ->form(fn (Order $record) => ReviewResource::orderReviewFields($record))
                        ->action(function (Order $record, array $data) {
                            if ($record->package_id && Review::where('user_id', $record->user_id)->where('package_id', $record->package_id)->exists()) {
                                Notification::make()->title(__('Anda sudah menulis ulasan untuk layanan ini.'))->warning()->send();

                                return;
                            }
                            if ($record->product_id && Review::where('user_id', $record->user_id)->where('product_id', $record->product_id)->exists()) {
                                Notification::make()->title(__('Anda sudah menulis ulasan untuk layanan ini.'))->warning()->send();

                                return;
                            }

                            Review::create([
                                'user_id' => $record->user_id,
                                'package_id' => $record->package_id,
                                'product_id' => $record->product_id,
                                'rating' => $data['rating'],
                                'title' => $data['title'] ?? null,
                                'comment' => $data['comment'] ?? null,
                                'photo' => $data['photo'] ?? null,
                            ]);

                            NativeNotificationHelper::success(__('Terima kasih atas ulasan Anda!'));
                        }),

                ])
                    ->label(__('Klik Tombol Grup'))
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->button()
                    ->size('sm')
                    ->color('gray'),
            ])
            ->actionsAlignment('center')
            ->headerActions([
                Tables\Actions\Action::make('clear_history')
                    ->label(__('Bersihkan Riwayat'))
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->button()
                    ->size('sm')
                    ->requiresConfirmation()
                    ->modalHeading(__('Bersihkan Riwayat Pesanan'))
                    ->modalDescription(__('Pilih jenis pesanan yang ingin Anda hapus secara permanen dari riwayat.'))
                    ->form([
                        Forms\Components\Tabs::make('Delete Options')
                            ->tabs([
                                Forms\Components\Tabs\Tab::make(__('Berdasarkan Status'))
                                    ->icon('heroicon-o-tag')
                                    ->schema([
                                        Forms\Components\Select::make('type')
                                            ->label(__('Hapus Pesanan Berdasarkan Status'))
                                            ->options([
                                                'all' => __('Semua Pesanan'),
                                                'cancelled' => __('Hanya Pesanan Dibatalkan'),
                                                'completed' => __('Hanya Pesanan Selesai'),
                                            ])
                                            ->default('all')
                                            ->native(false),
                                    ]),
                                Forms\Components\Tabs\Tab::make(__('Pilih Pesanan Spesifik'))
                                    ->icon('heroicon-o-list-bullet')
                                    ->schema([
                                        Forms\Components\CheckboxList::make('order_ids')
                                            ->label(__('Pilih Nomor Pesanan'))
                                            ->options(fn () => Order::where('user_id', auth()->id())
                                                ->latest()
                                                ->get()
                                                ->mapWithKeys(fn ($order) => [
                                                    $order->id => "#{$order->order_number} - ".($order->package?->name ?? __('Layanan')),
                                                ]))
                                            ->bulkToggleable()
                                            ->searchable(),
                                    ]),
                            ])->columnSpanFull(),
                    ])
                    ->action(function (array $data) {
                        $query = Order::query()->where('user_id', auth()->id());

                        if (! empty($data['order_ids'])) {
                            // Jika ada yang dipilih spesifik, hapus yang dipilih saja
                            $query->whereIn('id', $data['order_ids']);
                        } else {
                            // Jika tidak ada yang dipilih spesifik, gunakan pilihan status
                            if ($data['type'] === 'cancelled') {
                                $query->where('status', OrderStatus::CANCELLED);
                            } elseif ($data['type'] === 'completed') {
                                $query->where('status', OrderStatus::COMPLETED);
                            }
                        }

                        $query->delete();
                        Notification::make()
                            ->title(__('Riwayat Berhasil Dibersihkan'))
                            ->success()
                            ->send();
                    })
                    ->visible(fn () => Order::query()->where('user_id', auth()->id())->exists()),
            ]);

    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                // Alert/Status Box
                Infolists\Components\Section::make()
                    ->schema([
                        Infolists\Components\Grid::make(3)->schema([
                            Infolists\Components\TextEntry::make('status')
                                ->label(__('Status Pesanan'))
                                ->badge()
                                ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                            Infolists\Components\TextEntry::make('payment_status')
                                ->label(__('Status Pembayaran'))
                                ->badge()
                                ->size(Infolists\Components\TextEntry\TextEntrySize::Large),
                            Infolists\Components\TextEntry::make('order_number')
                                ->label(__('No. Pesanan'))
                                ->weight(FontWeight::Bold)
                                ->copyable(),
                        ]),
                    ])
                    ->extraAttributes(['class' => 'bg-gray-50 dark:bg-white/5 border-0 shadow-none rounded-2xl mb-4']),

                // Ordered Product
                Infolists\Components\Section::make(__('Paket Dipesan'))
                    ->icon('heroicon-o-shopping-bag')
                    ->iconColor('primary')
                    ->compact()
                    ->schema([
                        Infolists\Components\Grid::make()->schema([
                            Infolists\Components\ImageEntry::make('item_image')
                                ->state(fn ($record) => $record->package?->image_url ?? $record->product?->image_url)
                                ->hiddenLabel()
                                ->height('6rem')
                                ->width('6rem')
                                ->extraImgAttributes(['class' => 'rounded-xl object-cover shadow-sm'])
                                ->grow(false),
                            Infolists\Components\Group::make([
                                Infolists\Components\TextEntry::make('item_name')
                                    ->state(fn ($record) => $record->package?->name ?? $record->product?->name)
                                    ->formatStateUsing(fn ($state) => __($state))
                                    ->hiddenLabel()
                                    ->weight(FontWeight::Bold)
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large),

                                Infolists\Components\TextEntry::make('booking_date')
                                    ->label(__('Untuk Tanggal Acara:'))
                                    ->inlineLabel()
                                    ->date('d F Y')
                                    ->weight(FontWeight::Bold)
                                    ->color('primary'),
                                Infolists\Components\TextEntry::make('booking_time')
                                    ->label(__('Waktu:'))
                                    ->inlineLabel()
                                    ->time('H:i')
                                    ->weight(FontWeight::Bold)
                                    ->color('info'),
                                Infolists\Components\TextEntry::make('quantity')
                                    ->label(__('Jumlah:'))
                                    ->inlineLabel()
                                    ->badge()
                                    ->color('warning'),
                            ])->columnSpan(2),
                        ])->columns(3),
                    ]),

                // Pricing
                Infolists\Components\Section::make(__('Rincian Harga'))
                    ->icon('heroicon-o-currency-dollar')
                    ->iconColor('success')
                    ->compact()
                    ->schema([
                        Infolists\Components\TextEntry::make('total_price')
                            ->label(__('Total Pembayaran'))
                            ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 2, ',', '.'))
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight(FontWeight::Bold)
                            ->color('primary')
                            ->inlineLabel(),
                    ]),

                // Notes
                Infolists\Components\Section::make(__('Catatan Pemesan'))
                    ->icon('heroicon-o-document-text')
                    ->iconColor('gray')
                    ->compact()
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->formatStateUsing(fn ($state) => __($state))
                            ->hiddenLabel()

                            ->columnSpanFull(),
                    ]),

                // Metode Pembayaran & Countdown
                Infolists\Components\Section::make(__('Metode Pembayaran & Batas Waktu'))
                    ->icon('heroicon-o-credit-card')
                    ->iconColor('success')
                    ->compact()
                    ->schema([
                        Infolists\Components\ViewEntry::make('payment_info')
                            ->hiddenLabel()
                            ->view('User.components.order-payment-info.order-payment-info'),
                        Infolists\Components\Actions::make([
                            Infolists\Components\Actions\Action::make('upload_proof')
                                ->label(__('Upload Bukti Pembayaran'))
                                ->icon('heroicon-o-photo')
                                ->button()
                                ->color('success')
                                ->visible(fn (Order $record) => in_array($record->payment_status, [
                                    OrderPaymentStatus::UNPAID,
                                    OrderPaymentStatus::PENDING,
                                    OrderPaymentStatus::FAILED,
                                ]) && $record->status !== OrderStatus::CANCELLED)
                                ->modalHeading(__('Upload Bukti Pembayaran'))
                                ->modalDescription(__('Unggah bukti transfer / pembayaran Anda. Pesanan akan ditandai LUNAS.'))
                                ->form([
                                    Forms\Components\FileUpload::make('proof')
                                        ->label(__('Bukti Pembayaran'))
                                        ->image()
                                        ->directory('payment-proofs')
                                        ->disk('public')
                                        ->visibility('public')
                                        ->maxSize(5120)
                                        ->required(),
                                ])
                                ->action(function (Order $record, array $data) {
                                    $transaction = $record->latestTransaction;
                                    if ($transaction) {
                                        $transaction->update([
                                            'status' => 'success',
                                            'paid_at' => now(),
                                            'notes' => $data['proof'] ?? $transaction->notes,
                                        ]);
                                    }
                                    $record->update(['payment_status' => OrderPaymentStatus::PAID]);
                                    Notification::make()
                                        ->title(__('Bukti pembayaran diterima.'))
                                        ->body(__('Pesanan #:order telah ditandai LUNAS.', ['order' => $record->order_number]))
                                        ->success()
                                        ->send();
                                }),
                        ])->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageOrders::route('/'),
            'view' => ViewOrder::route('/{record}'),
            'edit' => EditOrder::route('/{record}/edit'),
        ];
    }
}
