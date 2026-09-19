<?php

namespace App\Filament\Admin\Resources\PaymentMethodResource;

use App\Filament\Admin\Resources\PaymentMethodResource\Pages;
use App\Models\PaymentMethod\PaymentMethod;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentMethodResource extends Resource
{
    protected static ?string $model = PaymentMethod::class;

    protected static ?string $slug = 'payment-methods';

    protected static ?string $navigationIcon = 'heroicon-o-credit-card';

    protected static ?int $navigationSort = 3;

    public static function getModelLabel(): string
    {
        return __('Metode Pembayaran');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Metode Pembayaran');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Transaksi');
    }

    public static function getNavigationLabel(): string
    {
        return __('Metode Pembayaran');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::$model::where('is_active', true)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Metode Pembayaran Aktif');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Informasi Metode Pembayaran'))
                    ->description(__('Nama, tipe, dan detail akun pembayaran.'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('Nama'))
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('type')
                            ->label(__('Tipe'))
                            ->required()
                            ->options([
                                'bank_transfer' => __('Transfer Bank'),
                                'e_wallet' => __('E-Wallet'),
                                'qris' => __('QRIS'),
                                'credit_card' => __('Kartu Kredit/Debit'),
                                'convenience_store' => __('Convenience Store'),
                            ]),
                        Forms\Components\TextInput::make('code')
                            ->label(__('Kode'))
                            ->maxLength(50),
                        Forms\Components\TextInput::make('deeplink')
                            ->label(__('Deep Link Aplikasi'))
                            ->maxLength(255)
                            ->helperText(__('Skema deep link untuk membuka aplikasi e-wallet (contoh: dana://, ovopay:///naga_luv_ov8). Kosongkan jika tidak ingin menautkan ke aplikasi.'))
                            ->placeholder('contoh: dana://'),
                        Forms\Components\TextInput::make('bank_name')
                            ->label(__('Nama Bank'))
                            ->maxLength(255),
                        Forms\Components\TextInput::make('account_number')
                            ->label(__('Nomor Rekening'))
                            ->maxLength(100),
                        Forms\Components\TextInput::make('account_holder')
                            ->label(__('Atas Nama'))
                            ->maxLength(255),
                        Forms\Components\FileUpload::make('image_url')
                            ->label(__('Logo / QR Image'))
                            ->image()
                            ->directory('payment-methods'),
                        Forms\Components\Textarea::make('instructions')
                            ->label(__('Instruksi Pembayaran'))
                            ->rows(4)
                            ->helperText(__('Langkah-langkah pembayaran untuk ditampilkan ke pengguna.')),
                        Forms\Components\TextInput::make('fee')
                            ->label(__('Biaya Admin (Rp)'))
                            ->numeric()
                            ->default(0)
                            ->prefix('Rp'),
                        Forms\Components\TextInput::make('sort_order')
                            ->label(__('Urutan'))
                            ->numeric()
                            ->default(0),
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('Aktif'))
                            ->default(true)
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([5])
            ->defaultSort('sort_order')
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label(__('Nama')),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('Tipe'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'bank_transfer' => 'warning',
                        'e_wallet' => 'success',
                        'qris' => 'info',
                        'credit_card' => 'primary',
                        'convenience_store' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'bank_transfer' => __('Transfer Bank'),
                        'e_wallet' => __('E-Wallet'),
                        'qris' => __('QRIS'),
                        'credit_card' => __('Kartu Kredit/Debit'),
                        'convenience_store' => __('Convenience Store'),
                        default => $state,
                    })
                    ->alignment('center'),
                Tables\Columns\TextColumn::make('bank_name')
                    ->label(__('Bank'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('account_number')
                    ->label(__('Rekening'))
                    ->searchable()
                    ->copyable()
                    ->copyableState(fn ($state) => $state),
                Tables\Columns\TextColumn::make('account_holder')
                    ->label(__('Atas Nama'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('fee')
                    ->label(__('Biaya'))
                    ->money('idr')
                    ->sortable()
                    ->alignment('end')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Aktif'))
                    ->boolean()
                    ->sortable()
                    ->alignment('center'),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('Urutan'))
                    ->numeric()
                    ->sortable()
                    ->alignment('center')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'bank_transfer' => __('Transfer Bank'),
                        'e_wallet' => __('E-Wallet'),
                        'qris' => __('QRIS'),
                        'credit_card' => __('Kartu Kredit/Debit'),
                        'convenience_store' => __('Convenience Store'),
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Aktif')),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->slideOver()
                    ->button()
                    ->color('info')
                    ->size('lg'),
                Tables\Actions\EditAction::make()
                    ->slideOver()
                    ->button()
                    ->color('warning')
                    ->size('lg')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('Metode Pembayaran diperbarui'))
                            ->body(__('Metode pembayaran berhasil diperbarui.'))
                    ),
                Tables\Actions\DeleteAction::make()
                    ->button()
                    ->color('danger')
                    ->size('lg')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('Metode Pembayaran dihapus'))
                            ->body(__('Metode pembayaran berhasil dihapus.'))
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePaymentMethods\ManagePaymentMethods::route('/'),
        ];
    }
}
