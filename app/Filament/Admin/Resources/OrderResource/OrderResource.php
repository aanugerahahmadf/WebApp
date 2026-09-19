<?php

namespace App\Filament\Admin\Resources\OrderResource;

use App\Enums\OrderPaymentStatus\OrderPaymentStatus;
use App\Enums\OrderStatus\OrderStatus;
use App\Filament\Admin\Pages\MessagesPage\MessagesPage;
use App\Models\Inbox\Inbox;
use App\Models\Message\Message;
use App\Models\Order\Order;
use App\Models\User\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Enums\FontWeight;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

/**
 * @mixin \Eloquent
 *
 * @property-read Order $record
 */
class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $slug = 'orders';

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'order_number';

    public static function getModelLabel(): string
    {
        return __('Pesanan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Pesanan');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['order_number'];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Transaksi');
    }

    public static function getNavigationLabel(): string
    {
        return __('Daftar Pesanan');
    }

    public static function getNavigationBadge(): ?string
    {
        /** @var Builder $query */
        $query = static::$model::query();

        return (string) $query->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Manajemen Pesanan Pelanggan');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make(__('Informasi Pelanggan & Layanan'))
                            ->description(__('Hubungkan pesanan ke pelanggan dan paket yang dipilih.'))
                            ->icon('heroicon-o-shopping-bag')
                            ->schema([
                                Forms\Components\Select::make('user_id')
                                    ->searchable()
                                    ->label(__('Pelanggan'))
                                    ->options(User::query()->pluck('full_name', 'id')->toArray())
                                    ->preload()
                                    ->prefixIcon('heroicon-o-user')
                                    ->required(),
                                Forms\Components\Select::make('package_id')
                                    ->searchable()
                                    ->label(__('Paket Layanan'))
                                    ->relationship('package', 'name')
                                    ->preload()
                                    ->prefixIcon('heroicon-o-gift')
                                    ->required(),
                            ])->columns(2),

                        Forms\Components\Section::make(__('Detail Eksekusi & Acara'))
                            ->description(__('Jadwal, referensi, dan instruksi penanganan dari pelanggan.'))
                            ->icon('heroicon-o-calendar-days')
                            ->schema([
                                Forms\Components\TextInput::make('order_number')
                                    ->label(__('Nomor Referensi'))
                                    ->required()
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-o-hashtag'),
                                Forms\Components\DatePicker::make('booking_date')
                                    ->label(__('Tanggal Acara (Booking)'))
                                    ->required()
                                    ->prefixIcon('heroicon-o-calendar'),
                                Forms\Components\RichEditor::make('notes')
                                    ->label(__('Catatan / Permintaan Khusus'))
                                    ->columnSpanFull()
                                    ->toolbarButtons(['bold', 'italic', 'underline', 'bulletList', 'orderedList']),
                            ])->columns(2),
                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make(__('Status & Keuangan'))
                            ->description(__('Pantau dan update perkembangan pembayaran dan layanan.'))
                            ->icon('heroicon-o-banknotes')
                            ->schema([
                                Forms\Components\TextInput::make('total_price')
                                    ->label(__('Total Harga (Tagihan)'))
                                    ->required()
                                    ->prefix('Rp')
                                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2, ',', '.'))
                                    ->dehydrateStateUsing(fn ($state) => $state ? (float) str_replace(',', '.', str_replace(['Rp', '.', ' '], '', $state)) : 0)
                                    ->extraInputAttributes(['class' => 'font-bold text-2xl text-primary-600']),
                                Forms\Components\Select::make('status')
                                    ->searchable()
                                    ->label(__('Status Pengerjaan'))
                                    ->options(OrderStatus::class)
                                    ->native(false)
                                    ->required(),
                                Forms\Components\Select::make('payment_status')
                                    ->searchable()
                                    ->label(__('Status Pembayaran'))
                                    ->options(OrderPaymentStatus::class)
                                    ->native(false)
                                    ->required(),
                            ]),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([5])
            ->columns([
                Tables\Columns\TextColumn::make('user.full_name')
                    ->label(__('Pelanggan'))
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-user'),
                Tables\Columns\TextColumn::make('package.name')
                    ->searchable()
                    ->label(__('Paket Layanan'))
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('order_number')
                    ->searchable()
                    ->label(__('No. Pesanan'))
                    ->badge()
                    ->color('primary')
                    ->copyable()
                    ->copyableState(fn ($state) => $state)
                    ->alignment('center')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_price')
                    ->label(__('Harga'))
                    ->money('IDR')
                    ->alignment('end')
                    ->sortable()
                    ->icon('heroicon-o-banknotes'),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->alignment('center')
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_status')
                    ->label(__('Pembayaran'))
                    ->badge()
                    ->alignment('center')
                    ->sortable(),
                Tables\Columns\TextColumn::make('booking_date')
                    ->label(__('Tanggal Acara'))
                    ->date('d M Y')
                    ->alignment('center')
                    ->sortable()
                    ->icon('heroicon-o-calendar'),
                Tables\Columns\TextColumn::make('booking_time')
                    ->label(__('Waktu'))
                    ->time('H:i')
                    ->alignment('center')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('quantity')
                    ->label(__('Jumlah'))
                    ->numeric()
                    ->alignment('center')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('notes')
                    ->label(__('Catatan'))
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Tanggal Pesan'))
                    ->dateTime()
                    ->alignment('center')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Terakhir Diperbarui'))
                    ->dateTime()
                    ->alignment('center')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('complete')
                        ->label(__('Selesaikan'))
                        ->icon('heroicon-m-check-badge')
                        ->color('success')
                        ->visible(fn ($record) => $record->status === OrderStatus::CONFIRMED)
                        ->requiresConfirmation()
                        ->successNotificationTitle(__('Pesanan Selesai'))
                        ->action(fn ($record) => $record->update(['status' => OrderStatus::COMPLETED])),

                    // ── Manual Payment Confirmation Actions ─────────────────
                    Tables\Actions\Action::make('mark_paid')
                        ->label(__('Tandai Dibayar'))
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->visible(fn ($record) => in_array($record?->payment_status?->value, ['unpaid', 'pending', 'failed']))
                        ->requiresConfirmation()
                        ->modalHeading(__('Konfirmasi Pembayaran'))
                        ->modalDescription(fn (Order $record) => __(
                            'Tandai pesanan #:order sebagai LUNAS? Pembayaran akan dikonfirmasi dan stok akan dikunci.',
                            ['order' => $record->order_number]
                        ))
                        ->modalSubmitActionLabel(__('Ya, Tandai Dibayar'))
                        ->action(function (Order $record) {
                            $transaction = $record->latestTransaction;
                            if ($transaction) {
                                $transaction->markAsSuccess();
                            } else {
                                $record->update([
                                    'status' => OrderStatus::CONFIRMED,
                                    'payment_status' => OrderPaymentStatus::PAID,
                                ]);
                            }
                            Notification::make()
                                ->title(__('Pembayaran Dikonfirmasi'))
                                ->body(__('Pesanan #:order telah ditandai LUNAS.', ['order' => $record->order_number]))
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\Action::make('mark_failed')
                        ->label(__('Tandai Gagal'))
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->visible(fn ($record) => in_array($record?->payment_status?->value, ['unpaid', 'pending']))
                        ->requiresConfirmation()
                        ->modalHeading(__('Tandai Pembayaran Gagal'))
                        ->modalDescription(fn (Order $record) => __(
                            'Tandai pembayaran pesanan #:order sebagai GAGAL?',
                            ['order' => $record->order_number]
                        ))
                        ->modalSubmitActionLabel(__('Ya, Tandai Gagal'))
                        ->action(function (Order $record) {
                            $transaction = $record->latestTransaction;
                            if ($transaction) {
                                $transaction->markAsFailed(__('Dibatalkan oleh admin'));
                            } else {
                                $record->update([
                                    'payment_status' => OrderPaymentStatus::FAILED,
                                ]);
                            }
                            Notification::make()
                                ->title(__('Pembayaran Gagal'))
                                ->body(__('Pembayaran pesanan #:order telah ditandai GAGAL.', ['order' => $record->order_number]))
                                ->danger()
                                ->send();
                        }),

                    Tables\Actions\Action::make('mark_pending')
                        ->label(__('Tandai Pending'))
                        ->icon('heroicon-o-clock')
                        ->color('warning')
                        ->visible(fn ($record) => $record?->payment_status?->value === 'failed')
                        ->requiresConfirmation()
                        ->modalHeading(__('Kembalikan ke Pending'))
                        ->modalDescription(fn (Order $record) => __(
                            'Kembalikan status pembayaran pesanan #:order menjadi MENUNGGU?',
                            ['order' => $record->order_number]
                        ))
                        ->modalSubmitActionLabel(__('Ya, Kembalikan'))
                        ->action(function (Order $record) {
                            $record->update([
                                'payment_status' => OrderPaymentStatus::PENDING,
                            ]);
                            $transaction = $record->latestTransaction;
                            if ($transaction) {
                                $transaction->update(['status' => 'pending']);
                            }
                            Notification::make()
                                ->title(__('Status Dikembalikan'))
                                ->body(__('Pembayaran pesanan #:order dikembalikan ke MENUNGGU.', ['order' => $record->order_number]))
                                ->warning()
                                ->send();
                        }),

                    // ── Kirim Notifikasi Pembayaran Manual ──────────────────
                    Tables\Actions\Action::make('send_payment_notification')
                        ->label(__('Kirim Notifikasi'))
                        ->icon('heroicon-o-bell-alert')
                        ->color('info')
                        ->requiresConfirmation()
                        ->modalHeading(__('Kirim Notifikasi Pembayaran'))
                        ->modalDescription(fn (Order $record) => __(
                            'Kirim notifikasi status pembayaran pesanan #:order ke :name via Email & WhatsApp.',
                            [
                                'order' => $record->order_number,
                                'name' => $record->user?->full_name ?? '-',
                            ]
                        ))
                        ->modalSubmitActionLabel(__('Kirim Sekarang'))
                        ->action(function (Order $record) {
                            $user = $record->user;
                            if (! $user) {
                                Notification::make()
                                    ->title(__('Gagal'))
                                    ->body(__('User tidak ditemukan.'))
                                    ->danger()
                                    ->send();

                                return;
                            }

                            try {
                                Notification::make()
                                    ->title(__('Notifikasi Terkirim!'))
                                    ->body(__('Email & WhatsApp telah dikirim ke ').$user->full_name)
                                    ->success()
                                    ->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title(__('Gagal Kirim Notifikasi'))
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),

                ])->label(__('Klik Tombol Grup'))
                    ->icon('heroicon-m-ellipsis-vertical')
                    ->size('lg')
                    ->color('primary')
                    ->button()
                    ->extraAttributes(['style' => 'min-width: 120px']),

                Tables\Actions\Action::make('chat')
                    ->label(__('Hubungi'))
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->button()
                    ->size('lg')
                    ->extraAttributes(['style' => 'min-width: 120px'])
                    ->url(function (Order $record) {
                        $authId = Auth::id();
                        $customerId = $record->user_id;
                        /** @var User|null $admin */
                        $admin = User::query()->role('super_admin')->first(['id']);
                        $adminId = $admin?->id ?? 1;

                        $targetId = ($authId == $customerId) ? $adminId : $customerId;

                        $inbox = Inbox::query()
                            ->whereJsonContains('user_ids', (int) $authId, 'and', false)
                            ->whereJsonContains('user_ids', (int) $targetId, 'and', false)
                            ->get(['*'])
                            /** @param Inbox $inbox */
                            ->first(function ($inbox) use ($authId, $targetId) {
                                $ids = collect($inbox->user_ids)->unique();

                                return $ids->contains($authId) && $ids->contains($targetId) && $ids->count() <= 2;
                            });

                        if (! $inbox) {
                            $inbox = Inbox::create([
                                'user_ids' => collect([(int) $authId, (int) $targetId])->unique()->values()->toArray(),
                                'title' => __('Diskusi Order #').$record->order_number,
                            ]);

                            Message::create([
                                'inbox_id' => $inbox->id,
                                'user_id' => $authId,
                                'message' => __('Halo, saya ingin mendiskusikan Pesanan #').$record->order_number.'.',
                                'read_by' => [$authId],
                            ]);
                        }

                        return MessagesPage::getUrl().'/'.$inbox->id;
                    }),
                Tables\Actions\ViewAction::make()
                    ->button()
                    ->color('info')
                    ->size('lg')
                    ->extraAttributes(['style' => 'min-width: 120px']),
                Tables\Actions\EditAction::make()
                    ->button()
                    ->color('warning')
                    ->size('lg')
                    ->extraAttributes(['style' => 'min-width: 120px'])
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('Pesanan diperbarui'))
                            ->body(__('Pesanan telah berhasil diperbarui.'))
                    ),
                Tables\Actions\DeleteAction::make()
                    ->button()
                    ->color('danger')
                    ->size('lg')
                    ->extraAttributes(['style' => 'min-width: 120px'])
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('Pesanan dihapus'))
                            ->body(__('Pesanan telah berhasil dihapus.'))
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\TransactionsRelationManager\TransactionsRelationManager::class,
        ];
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
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
                    ->extraAttributes(['class' => 'bg-gray-50 dark:bg-white/5 border-0 shadow-none rounded-2xl']),

                Infolists\Components\Section::make(__('Pelanggan'))
                    ->icon('heroicon-o-user')
                    ->iconColor('info')
                    ->compact()
                    ->schema([
                        Infolists\Components\Grid::make(2)->schema([
                            Infolists\Components\TextEntry::make('user.full_name')
                                ->label(__('Nama'))
                                ->weight(FontWeight::Bold),
                            Infolists\Components\TextEntry::make('user.email')
                                ->label(__('Email'))
                                ->color('gray'),
                            Infolists\Components\TextEntry::make('user.phone')
                                ->label(__('Telepon'))
                                ->icon('heroicon-o-phone')
                                ->color('gray')
                                ->placeholder('-'),
                            Infolists\Components\TextEntry::make('user.whatsapp')
                                ->label(__('WhatsApp'))
                                ->icon('heroicon-o-chat-bubble-left-ellipsis')
                                ->color('success')
                                ->placeholder(__('Belum diisi')),
                        ]),
                    ]),

                Infolists\Components\Section::make(__('Paket / Produk Dipesan'))
                    ->icon('heroicon-o-shopping-bag')
                    ->iconColor('primary')
                    ->compact()
                    ->schema([
                        Infolists\Components\Grid::make()->schema([
                            Infolists\Components\ImageEntry::make('package.image_url')
                                ->hiddenLabel()
                                ->height('6rem')
                                ->width('6rem')
                                ->extraImgAttributes(['class' => 'rounded-xl object-cover shadow-sm'])
                                ->grow(false)
                                ->visible(fn ($record) => (bool) $record->package_id),
                            Infolists\Components\ImageEntry::make('product.image_url')
                                ->hiddenLabel()
                                ->height('6rem')
                                ->width('6rem')
                                ->extraImgAttributes(['class' => 'rounded-xl object-cover shadow-sm'])
                                ->grow(false)
                                ->visible(fn ($record) => (bool) $record->product_id),
                            Infolists\Components\Group::make([
                                Infolists\Components\TextEntry::make('package.name')
                                    ->hiddenLabel()
                                    ->weight(FontWeight::Bold)
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->visible(fn ($record) => (bool) $record->package_id),
                                Infolists\Components\TextEntry::make('product.name')
                                    ->hiddenLabel()
                                    ->weight(FontWeight::Bold)
                                    ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                                    ->visible(fn ($record) => (bool) $record->product_id),

                                Infolists\Components\TextEntry::make('booking_date')
                                    ->label(__('Tanggal Acara'))
                                    ->inlineLabel()
                                    ->date('d F Y')
                                    ->weight(FontWeight::Bold)
                                    ->color('primary'),
                                Infolists\Components\TextEntry::make('booking_time')
                                    ->label(__('Waktu'))
                                    ->inlineLabel()
                                    ->time('H:i')
                                    ->color('info'),
                                Infolists\Components\TextEntry::make('quantity')
                                    ->label(__('Jumlah'))
                                    ->inlineLabel()
                                    ->badge()
                                    ->color('warning'),
                            ])->columnSpan(2),
                        ])->columns(3),
                    ]),

                Infolists\Components\Section::make(__('Rincian Harga'))
                    ->icon('heroicon-o-banknotes')
                    ->iconColor('success')
                    ->compact()
                    ->schema([
                        Infolists\Components\TextEntry::make('total_price')
                            ->label(__('Total Pembayaran'))
                            ->formatStateUsing(fn ($state) => 'Rp '.number_format($state, 2, ',', '.'))
                            ->size(Infolists\Components\TextEntry\TextEntrySize::Large)
                            ->weight(FontWeight::Bold)
                            ->color('success')
                            ->inlineLabel(),
                    ]),

                Infolists\Components\Section::make(__('Catatan'))
                    ->icon('heroicon-o-document-text')
                    ->iconColor('gray')
                    ->compact()
                    ->schema([
                        Infolists\Components\TextEntry::make('notes')
                            ->hiddenLabel()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders\ListOrders::route('/'),
            'create' => Pages\CreateOrder\CreateOrder::route('/create'),
            'view' => Pages\ViewOrder\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder\EditOrder::route('/{record}/edit'),
        ];
    }
}
