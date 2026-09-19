<?php

namespace App\Filament\Admin\Resources\ProductResource;

use App\Filament\Admin\Exports\ProductExporter\ProductExporter;
use App\Filament\Admin\Resources\ProductResource\Pages;
use App\Models\Product\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $slug = 'products';

    protected static ?string $navigationIcon = 'ri-flower-line';

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('Bunga');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Bunga');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Data Master');
    }

    public static function getNavigationLabel(): string
    {
        return __('Daftar Bunga');
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
        return __('Total Product');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'slug', 'description'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Informasi Product'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('Nama Product'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', str($state)->slug())),
                        Forms\Components\TextInput::make('slug')
                            ->label(__('Slug'))
                            ->required()
                            ->unique(ignorable: fn (?Product $record) => $record)
                            ->maxLength(255),
                        Forms\Components\Select::make('category_id')
                            ->label(__('Kategori'))
                            ->relationship('category', 'name', fn ($query) => $query->forProducts())
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('price')
                            ->label(__('Harga'))
                            ->numeric()
                            ->prefix('Rp')
                            ->required(),
                        Forms\Components\TextInput::make('discount_price')
                            ->label(__('Harga Diskon'))
                            ->numeric()
                            ->prefix('Rp'),
                        Forms\Components\TextInput::make('stock')
                            ->label(__('Stok'))
                            ->numeric()
                            ->default(0)
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('Aktif'))
                            ->default(true),
                        Forms\Components\Select::make('vendor_id')
                            ->label(__('Vendor'))
                            ->relationship('vendor', 'store_name')
                            ->searchable()
                            ->preload()
                            ->prefixIcon('heroicon-o-user'),
                        Forms\Components\RichEditor::make('description')
                            ->label(__('Deskripsi'))
                            ->columnSpanFull(),
                        Forms\Components\SpatieMediaLibraryFileUpload::make('image')
                            ->label(__('Foto Product'))
                            ->collection('product_image')
                            ->image()
                            ->multiple()
                            ->imageEditor()
                            ->formatStateUsing(fn (mixed $state): mixed => static::sanitizeSpatieUploadState($state))
                            ->afterStateHydrated(
                                fn (Forms\Components\SpatieMediaLibraryFileUpload $component, mixed $state): mixed => $component->state(static::sanitizeSpatieUploadState($state))
                            )
                            ->getUploadedFileUsing(
                                fn (Forms\Components\SpatieMediaLibraryFileUpload $component, mixed $file): ?array => static::safeUploadedMediaFileData($component, $file)
                            )
                            ->maxSize(102400000)
                            ->columnSpanFull(),

                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([5])
            ->columns([
                Tables\Columns\SpatieMediaLibraryImageColumn::make('product_image')
                    ->label(__('Foto'))
                    ->collection('product_image')
                    ->defaultImageUrl(asset('images/placeholders/image-placeholder.png'))
                    ->height(56)
                    ->width(56)
                    ->extraImgAttributes([
                        'class' => 'rounded-lg object-cover w-14 h-14',
                        'style' => 'min-width:3.5rem;min-height:3.5rem;',
                    ])
                    ->alignment('center'),
                Tables\Columns\TextColumn::make('name')
                    ->label(__('Nama Produk'))
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-sparkles'),
                Tables\Columns\TextColumn::make('category.name')
                    ->label(__('Kategori'))
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('vendor.store_name')
                    ->label(__('Vendor'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-o-user'),
                Tables\Columns\TextColumn::make('price')
                    ->label(__('Harga'))
                    ->money('IDR')
                    ->sortable()
                    ->alignment('end')
                    ->icon('heroicon-o-banknotes'),
                Tables\Columns\TextColumn::make('discount_price')
                    ->label(__('Harga Diskon'))
                    ->money('IDR')
                    ->sortable()
                    ->alignment('end')
                    ->color('success')
                    ->icon('heroicon-o-tag')
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('stock')
                    ->label(__('Stok'))
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        $state > 10 => 'success',
                        $state > 0 => 'warning',
                        default => 'danger',
                    })
                    ->sortable()
                    ->alignment('center'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Status'))
                    ->boolean()
                    ->sortable()
                    ->alignment('center'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name'),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->url(fn (Product $record): string => static::getUrl('view', ['record' => $record]))
                    ->button()
                    ->color('info')
                    ->size('lg'),
                Tables\Actions\EditAction::make()
                    ->url(fn (Product $record): string => static::getUrl('edit', ['record' => $record]))
                    ->button()
                    ->color('warning')
                    ->size('lg')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('Product diperbarui'))
                            ->body(__('Product telah berhasil diperbarui.'))
                    ),
                Tables\Actions\DeleteAction::make()
                    ->button()
                    ->color('danger')
                    ->size('lg')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('Product dihapus'))
                            ->body(__('Product telah berhasil dihapus.'))
                    ),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\ExportBulkAction::make()
                        ->exporter(ProductExporter::class)
                        ->label(__('Ekspor Data Terpilih')),
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts\ListProducts::route('/'),
            'create' => Pages\CreateProduct\CreateProduct::route('/create'),
            'view' => Pages\ViewProduct\ViewProduct::route('/{record}'),
            'edit' => Pages\EditProduct\EditProduct::route('/{record}/edit'),
        ];
    }

    /**
     * Strip null / empty values from Spatie upload state so the vendor
     * getUploadedFileUsing closure never receives null as $file (PHP 8.5+
     * strict type enforcement would throw TypeError otherwise).
     */
    private static function sanitizeSpatieUploadState(mixed $state): array
    {
        return collect(is_array($state) ? $state : [$state])
            ->filter(fn (mixed $item): bool => is_string($item) && $item !== '')
            ->mapWithKeys(fn (string $item): array => [$item => $item])
            ->all();
    }

    private static function safeUploadedMediaFileData(Forms\Components\SpatieMediaLibraryFileUpload $component, mixed $file): ?array
    {
        if (! is_string($file) || $file === '' || ! $component->getRecord()) {
            return null;
        }

        /** @var Media|null $media */
        $media = $component->getRecord()->getRelationValue('media')?->firstWhere('uuid', $file);

        if (! $media) {
            return null;
        }

        $url = $component->getConversion() && $media->hasGeneratedConversion($component->getConversion())
            ? $media->getUrl($component->getConversion())
            : $media->getUrl();

        return [
            'name' => $media->getAttributeValue('name') ?? $media->getAttributeValue('file_name'),
            'size' => $media->getAttributeValue('size'),
            'type' => $media->getAttributeValue('mime_type'),
            'url' => $url,
        ];
    }
}
