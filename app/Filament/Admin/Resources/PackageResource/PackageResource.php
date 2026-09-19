<?php

namespace App\Filament\Admin\Resources\PackageResource;

use App\Filament\Admin\Resources\PackageResource\Pages;
use App\Models\Package\Package;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @mixin \Eloquent
 *
 * @property-read Package $record
 */
class PackageResource extends Resource
{
    protected static ?string $model = Package::class;

    protected static ?string $slug = 'packages';

    protected static ?string $navigationIcon = 'ri-gift-line';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function getModelLabel(): string
    {
        return __('Paket Dekorasi');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Paket Dekorasi');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'theme', 'color'];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Data Master');
    }

    public static function getNavigationLabel(): string
    {
        return __('Paket Dekorasi');
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
        return __('Total Paket Dekorasi');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make(__('Informasi Utama'))
                            ->description(__('Penamaan dan deskripsi paket dekorasi.'))
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label(__('Nama Paket'))
                                    ->required()
                                    ->maxLength(255)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(fn (Forms\Set $set, ?string $state) => $set('slug', str($state)->slug()))
                                    ->prefixIcon('heroicon-o-gift'),
                                Forms\Components\TextInput::make('slug')
                                    ->label(__('Slug'))
                                    ->required()
                                    ->unique(ignorable: fn (?Package $record) => $record)
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-o-link'),

                                Forms\Components\Select::make('category_id')
                                    ->searchable()
                                    ->label(__('Kategori Dekorasi'))
                                    ->relationship('category', 'name', fn ($query) => $query->forPackages())
                                    ->preload()
                                    ->prefixIcon('heroicon-o-tag')
                                    ->columnSpanFull()
                                    ->required(),
                                Forms\Components\TextInput::make('stock')
                                    ->label(__('Stok / Kuota Tersedia'))
                                    ->helperText(__('Jumlah paket yang tersedia untuk dipesan.'))
                                    ->numeric()
                                    ->default(10)
                                    ->required()
                                    ->prefixIcon('heroicon-o-archive-box'),
                                Forms\Components\RichEditor::make('description')
                                    ->label(__('Deskripsi Lengkap'))
                                    ->columnSpanFull()
                                    ->toolbarButtons([
                                        'bold', 'italic', 'underline', 'strike', 'link', 'h2', 'h3', 'bulletList', 'orderedList', 'redo', 'undo',
                                    ]),

                            ])->columns(2),

                        Forms\Components\Section::make(__('Harga & Fitur'))
                            ->description(__('Informasi finansial dan fasilitas yang didapatkan.'))
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Forms\Components\TextInput::make('price')
                                    ->label(__('Harga Dasar'))
                                    ->required()
                                    ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 2, ',', '.') : null)
                                    ->dehydrateStateUsing(fn ($state) => $state ? (float) str_replace(',', '.', str_replace(['Rp', '.', ' '], '', $state)) : null)
                                    ->prefix('Rp')
                                    ->extraInputAttributes(['class' => 'font-bold text-lg text-primary-600']),
                                Forms\Components\TextInput::make('discount_price')
                                    ->label(__('Harga Diskon'))
                                    ->formatStateUsing(fn ($state) => $state ? number_format((float) $state, 2, ',', '.') : null)
                                    ->dehydrateStateUsing(fn ($state) => $state ? (float) str_replace(',', '.', str_replace(['Rp', '.', ' '], '', $state)) : null)
                                    ->prefix('Rp')
                                    ->validationAttribute('price')
                                    ->rules(['nullable']),
                                Forms\Components\TagsInput::make('features')
                                    ->label(__('Fitur Paket'))

                                    ->color('primary')
                                    ->columnSpanFull(),
                            ])->columns(2),

                    ])->columnSpan(['lg' => 2]),

                Forms\Components\Group::make()
                    ->schema([
                        Forms\Components\Section::make(__('Media Portfolio'))
                            ->description(__('Upload foto utama dan video presentasi dari paket ini.'))
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Forms\Components\SpatieMediaLibraryFileUpload::make('image')
                                    ->label(__('Foto Paket (boleh lebih dari satu)'))
                                    ->collection('package_image')
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
                                Forms\Components\SpatieMediaLibraryFileUpload::make('videos')
                                    ->label(__('Video Portfolio'))
                                    ->collection('videos')
                                    ->multiple()
                                    ->formatStateUsing(fn (mixed $state): mixed => static::sanitizeSpatieUploadState($state))
                                    ->afterStateHydrated(
                                        fn (Forms\Components\SpatieMediaLibraryFileUpload $component, mixed $state): mixed => $component->state(static::sanitizeSpatieUploadState($state))
                                    )
                                    ->getUploadedFileUsing(
                                        fn (Forms\Components\SpatieMediaLibraryFileUpload $component, mixed $file): ?array => static::safeUploadedMediaFileData($component, $file)
                                    )
                                    ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/ogg', 'video/quicktime'])
                                    ->maxSize(102400000)
                                    ->maxFiles(5)
                                    ->helperText(__('Upload video portfolio paket. Format: MP4, WebM, MOV. Maks 100GB per file.'))
                                    ->columnSpanFull(),
                            ]),

                        Forms\Components\Section::make(__('Status & Klasifikasi'))
                            ->icon('heroicon-o-sparkles')
                            ->schema([
                                Forms\Components\Toggle::make('is_featured')
                                    ->label(__('Paket Unggulan'))
                                    ->helperText(__('Tampilkan paket ini di halaman rekomendasi.'))
                                    ->onIcon('heroicon-s-star')
                                    ->offIcon('heroicon-o-star')
                                    ->onColor('warning'),
                            ]),

                        Forms\Components\Section::make(__('Tema & Kapasitas'))
                            ->icon('heroicon-o-users')
                            ->schema([
                                Forms\Components\ColorPicker::make('color')
                                    ->label(__('Warna Aksen')),
                            ]),
                        Forms\Components\Select::make('vendor_id')
                            ->label(__('Vendor'))
                            ->relationship('vendor', 'store_name')
                            ->searchable()
                            ->preload()
                            ->prefixIcon('heroicon-o-user'),
                    ])->columnSpan(['lg' => 1]),
            ])->columns(3);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([5])
            ->columns([
                SpatieMediaLibraryImageColumn::make('package_image')
                    ->label(__('Foto'))
                    ->collection('package_image')
                    ->defaultImageUrl(asset('images/placeholders/image-placeholder.png'))
                    ->height(56)
                    ->width(56)
                    ->extraImgAttributes([
                        'class' => 'rounded-lg object-cover w-14 h-14',
                        'style' => 'min-width:3.5rem;min-height:3.5rem;',
                    ])
                    ->alignment('center'),
                Tables\Columns\TextColumn::make('category.name')
                    ->searchable()
                    ->label(__('Kategori'))
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->alignment('center'),
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->label(__('Nama Paket'))
                    ->sortable()
                    ->icon('heroicon-o-gift'),
                Tables\Columns\TextColumn::make('vendor.store_name')
                    ->label(__('Vendor'))
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('success')
                    ->icon('heroicon-o-user'),
                Tables\Columns\TextColumn::make('price')
                    ->label(__('Harga Dasar'))
                    ->money('IDR')
                    ->sortable()
                    ->alignment('end')
                    ->icon('heroicon-o-banknotes'),
                Tables\Columns\TextColumn::make('stock')
                    ->label(__('Stok'))
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        $state <= 0 => 'danger',
                        $state <= 5 => 'warning',
                        default => 'success',
                    })
                    ->numeric()
                    ->sortable()
                    ->alignment('center'),
                Tables\Columns\IconColumn::make('is_featured')
                    ->label(__('Unggulan'))
                    ->boolean()
                    ->alignment('center')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('slug')
                    ->label(__('Slug'))
                    ->searchable()
                    ->badge()
                    ->color('gray')
                    ->copyable()
                    ->copyableState(fn ($state) => $state)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('description')
                    ->label(__('Deskripsi'))
                    ->limit(50)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Dibuat Pada'))
                    ->dateTime()
                    ->alignment('center')
                    ->sortable()
                    ->icon('heroicon-o-calendar')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Diperbarui Pada'))
                    ->dateTime()
                    ->alignment('center')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->button()
                    ->color('info')
                    ->size('lg'),
                Tables\Actions\EditAction::make()
                    ->button()
                    ->color('warning')
                    ->size('lg')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('Paket diperbarui'))
                            ->body(__('Paket telah berhasil diperbarui.'))
                    ),
                Tables\Actions\DeleteAction::make()
                    ->button()
                    ->color('danger')
                    ->size('lg')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('Paket dihapus'))
                            ->body(__('Paket telah berhasil dihapus.'))
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
            'index' => Pages\ListPackages\ListPackages::route('/'),
            'create' => Pages\CreatePackage\CreatePackage::route('/create'),
            'view' => Pages\ViewPackage\ViewPackage::route('/{record}'),
            'edit' => Pages\EditPackage\EditPackage::route('/{record}/edit'),
        ];
    }

    private static function sanitizeSpatieUploadState(mixed $state): array
    {
        return collect(is_array($state) ? $state : [$state])
            ->filter(fn (mixed $product): bool => is_string($product) && $product !== '')
            ->mapWithKeys(fn (string $product): array => [$product => $product])
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
