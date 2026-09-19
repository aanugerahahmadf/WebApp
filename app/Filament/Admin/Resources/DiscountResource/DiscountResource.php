<?php

namespace App\Filament\Admin\Resources\DiscountResource;

use App\Enums\DiscountType\DiscountType;
use App\Filament\Admin\Resources\DiscountResource\Pages;
use App\Models\Discount\Discount;
use App\Models\Package\Package;
use App\Models\Product\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DiscountResource extends Resource
{
    protected static ?string $model = Discount::class;

    protected static ?string $slug = 'discounts';

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'id';

    public static function getModelLabel(): string
    {
        return __('Diskon');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Diskon');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Transaksi');
    }

    public static function getNavigationLabel(): string
    {
        return __('Diskon Item');
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
        return __('Total Diskon Aktif');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['description'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Detail Diskon'))
                    ->schema([
                        Forms\Components\Textarea::make('description')
                            ->label(__('Deskripsi'))
                            ->maxLength(500),
                        Forms\Components\Select::make('discountable_type')
                            ->label(__('Tipe Item'))
                            ->options([
                                Package::class => __('Paket'),
                                Product::class => __('Produk'),
                            ])
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn (Forms\Set $set) => $set('discountable_id', null)),
                        Forms\Components\Select::make('discountable_id')
                            ->label(__('Item'))
                            ->options(function (Forms\Get $get) {
                                $type = $get('discountable_type');
                                if ($type === Package::class) {
                                    return Package::pluck('name', 'id');
                                }
                                if ($type === Product::class) {
                                    return Product::pluck('name', 'id');
                                }
                                return [];
                            })
                            ->required()
                            ->searchable(),
                    ])->columns(['sm' => 2]),

                Forms\Components\Section::make(__('Nilai Diskon'))
                    ->schema([
                        Forms\Components\ToggleButtons::make('type')
                            ->label(__('Tipe'))
                            ->options(DiscountType::class)
                            ->default(DiscountType::PERCENTAGE)
                            ->required()
                            ->inline(),
                        Forms\Components\TextInput::make('value')
                            ->label(__('Nilai'))
                            ->required()
                            ->numeric()
                            ->minValue(0)
                            ->prefix('Rp / %'),
                        Forms\Components\TextInput::make('min_purchase')
                            ->label(__('Min. Pembelian'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->prefix('Rp'),
                    ])->columns(['sm' => 3]),

                Forms\Components\Section::make(__('Periode Berlaku'))
                    ->schema([
                        Forms\Components\DateTimePicker::make('start_date')
                            ->label(__('Mulai')),
                        Forms\Components\DateTimePicker::make('end_date')
                            ->label(__('Berakhir')),
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('Aktif'))
                            ->default(true),
                    ])->columns(['sm' => 3]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('discountable_type')
                    ->label(__('Tipe Item'))
                    ->formatStateUsing(fn (string $state): string => $state === Package::class ? __('Paket') : __('Produk'))
                    ->badge()
                    ->color(fn (string $state): string => $state === Package::class ? 'warning' : 'info'),
                Tables\Columns\TextColumn::make('discountable.name')
                    ->label(__('Item'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label(__('Tipe Diskon'))
                    ->badge()
                    ->color(fn (DiscountType $state): string => match ($state) {
                        DiscountType::PERCENTAGE => 'success',
                        DiscountType::FIXED => 'info',
                    })
                    ->formatStateUsing(fn (DiscountType $state): string => match ($state) {
                        DiscountType::PERCENTAGE => __('Persentase (%)'),
                        DiscountType::FIXED => __('Nominal (Rp)'),
                    }),
                Tables\Columns\TextColumn::make('value')
                    ->label(__('Nilai'))
                    ->formatStateUsing(fn ($state, Discount $record) => $record->type === DiscountType::PERCENTAGE ? number_format((float) $state, 0).'%' : 'Rp '.number_format((float) $state, 0, ',', '.'))
                    ->alignment('center'),
                Tables\Columns\TextColumn::make('min_purchase')
                    ->label(__('Min. Pembelian'))
                    ->formatStateUsing(fn ($state) => 'Rp '.number_format((float) ($state ?? 0), 0, ',', '.'))
                    ->alignment('center')
                    ->sortable(),
                Tables\Columns\TextColumn::make('start_date')
                    ->label(__('Mulai'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('end_date')
                    ->label(__('Berakhir'))
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Aktif'))
                    ->boolean()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Status Aktif')),
                Tables\Filters\SelectFilter::make('discountable_type')
                    ->label(__('Tipe Item'))
                    ->options([
                        Package::class => __('Paket'),
                        Product::class => __('Produk'),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->slideOver()
                    ->button()
                    ->color('info'),
                Tables\Actions\EditAction::make()
                    ->slideOver()
                    ->button()
                    ->color('warning'),
                Tables\Actions\DeleteAction::make()
                    ->button()
                    ->color('danger'),
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
            'index' => Pages\ManageDiscounts\ManageDiscounts::route('/'),
        ];
    }
}
