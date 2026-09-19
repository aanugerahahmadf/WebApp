<?php

namespace App\Filament\Admin\Resources\VoucherResource;

use App\Enums\DiscountType\DiscountType;
use App\Filament\Admin\Resources\VoucherResource\Pages;
use App\Models\Discount\Discount;
use App\Models\Voucher\Voucher;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * @mixin \Eloquent
 *
 * @property-read Voucher $record
 */
class VoucherResource extends Resource
{
    protected static ?string $model = Voucher::class;

    protected static ?string $slug = 'vouchers';

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'code';

    public static function getModelLabel(): string
    {
        return __('Voucher');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Voucher');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Transaksi');
    }

    public static function getNavigationLabel(): string
    {
        return __('Voucher Promo');
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
        return __('Total Voucher Promo');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'description'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Detail Voucher'))
                    ->description(__('Kode voucher dan diskon yang diberikan.'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('Nama Voucher'))
                            ->maxLength(255),
                        Forms\Components\TextInput::make('code')
                            ->label(__('Kode Voucher'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('description')
                            ->label(__('Deskripsi'))
                            ->maxLength(255),
                        Forms\Components\Select::make('discount_id')
                            ->label(__('Diskon'))
                            ->relationship('discount', 'name')
                            ->getOptionLabelFromRecordUsing(fn (Discount $d) => $d->name.' — '.($d->type === DiscountType::PERCENTAGE ? number_format((float) $d->value, 0).'%' : 'Rp '.number_format((float) $d->value, 0, ',', '.')))
                            ->searchable()
                            ->preload()
                            ->helperText(__('Pilih diskon yang akan diberikan oleh voucher ini.')),
                    ])->columns(['sm' => 2]),

                Forms\Components\Section::make(__('Pengaturan Ketersediaan'))
                    ->description(__('Kelola waktu berlaku voucher dan statusnya.'))
                    ->schema([
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label(__('Tanggal Kadaluarsa')),
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('Status Aktif'))
                            ->default(true)
                            ->required(),
                        Forms\Components\Toggle::make('is_global')
                            ->label(__('Voucher Global'))
                            ->helperText(__('Jika aktif, semua user bisa pakai tanpa di-assign khusus.'))
                            ->default(false)
                            ->reactive(),
                        Forms\Components\TextInput::make('max_uses')
                            ->label(__('Maks. Pemakaian'))
                            ->numeric()
                            ->helperText(__('Total pemakaian maksimal voucher ini.')),
                    ])->columns(['sm' => 2]),

                Forms\Components\Section::make(__('Distribusi ke User'))
                    ->description(__('Assign voucher ini ke user tertentu. Kosongkan jika voucher global.'))
                    ->icon('heroicon-o-users')
                    ->schema([
                        Forms\Components\Select::make('users')
                            ->searchable()
                            ->label(__('Pilih User'))
                            ->multiple()
                            ->relationship('users', 'email')
                            ->preload()
                            ->helperText(__('User yang dipilih akan melihat voucher ini di halaman Voucher mereka.')),
                    ])
                    ->collapsed()
                    ->visible(fn (Forms\Get $get) => ! $get('is_global')),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([5])
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label(__('Nama')),
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->label(__('Kode')),
                Tables\Columns\TextColumn::make('discount.name')
                    ->label(__('Diskon'))
                    ->searchable()
                    ->sortable()
                    ->formatStateUsing(function ($state, Voucher $record) {
                        if (! $record->discount) {
                            return '-';
                        }
                        $d = $record->discount;
                        if ($d->type === DiscountType::PERCENTAGE) {
                            return number_format((float) $d->value, 0).'%';
                        }
                        return 'Rp '.number_format((float) $d->value, 0, ',', '.');
                    })
                    ->alignment('center'),
                Tables\Columns\TextColumn::make('discount.type')
                    ->label(__('Tipe'))
                    ->badge()
                    ->color(fn (?DiscountType $state): string => match ($state) {
                        DiscountType::PERCENTAGE => 'success',
                        DiscountType::FIXED => 'info',
                        null => 'gray',
                    })
                    ->formatStateUsing(fn (?DiscountType $state): string => match ($state) {
                        DiscountType::PERCENTAGE => __('Persentase (%)'),
                        DiscountType::FIXED => __('Nominal (Rp)'),
                        null => '-',
                    })
                    ->alignment('center'),
                Tables\Columns\TextColumn::make('expires_at')
                    ->label(__('Kadaluarsa Pada'))
                    ->dateTime()
                    ->sortable()
                    ->alignment('center'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Status'))
                    ->boolean()
                    ->sortable()
                    ->alignment('center'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Dibuat Pada'))
                    ->dateTime()
                    ->sortable()
                    ->alignment('center')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Status Aktif')),
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
                            ->title(__('Voucher diperbarui'))
                            ->body(__('Voucher telah berhasil diperbarui.'))
                    ),
                Tables\Actions\DeleteAction::make()
                    ->button()
                    ->color('danger')
                    ->size('lg')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('Voucher dihapus'))
                            ->body(__('Voucher telah berhasil dihapus.'))
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
            'index' => Pages\ManageVouchers\ManageVouchers::route('/'),
        ];
    }
}
