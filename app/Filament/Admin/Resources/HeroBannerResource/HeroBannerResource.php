<?php

namespace App\Filament\Admin\Resources\HeroBannerResource;

use App\Filament\Admin\Resources\HeroBannerResource\Pages\ManageHeroBanners;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Support\AdminAccess\AdminAccess;
use App\Support\AppIcon\AppIcon;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HeroBannerResource extends Resource
{
    protected static ?string $model = \App\Models\Home\HeroBanner::class;

    protected static ?string $slug = 'hero-banners';

    protected static ?string $navigationIcon = AppIcon::SPARKLES;

    protected static ?int $navigationSort = 304;

    public static function canViewAny(): bool
    {
        return AdminAccess::allows('view_customizations');
    }

    public static function canCreate(): bool
    {
        return AdminAccess::allows('manage_customizations');
    }

    public static function canEdit(Model $record): bool
    {
        return AdminAccess::allows('manage_customizations');
    }

    public static function canDelete(Model $record): bool
    {
        return AdminAccess::allows('manage_customizations');
    }

    public static function canDeleteAny(): bool
    {
        return AdminAccess::allows('manage_customizations');
    }

    public static function getNavigationLabel(): string
    {
        return __('Banner Promosi');
    }

    public static function getModelLabel(): string
    {
        return __('Banner Promosi');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Banner Promosi');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Konten');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['title', 'subtitle', 'link_url'];
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('title')
                ->label(__('Judul banner'))
                ->required()
                ->maxLength(255),
            Textarea::make('subtitle')
                ->label(__('Subjudul'))
                ->rows(3)
                ->maxLength(500),
            FileUpload::make('image_url')
                ->label(__('Gambar banner'))
                ->disk('public')
                ->directory('promotional-banners')
                ->visibility('public')
                ->image()
                ->imageEditor()
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                ->maxSize(8192)
                ->required()
                ->helperText(__('Gunakan rasio lebar, ideal 1920×600. Maksimal 8 MB.'))
                ->columnSpanFull(),
            Select::make('link_type')
                ->label(__('Jenis tautan'))
                ->options([
                    'catalog' => __('Katalog'),
                    'package' => __('Paket'),
                    'category' => __('Kategori'),
                    'promotion' => __('Promosi'),
                    'external' => __('URL eksternal'),
                    'page' => __('Halaman'),
                ])
                ->default('promotion')
                ->required(),
            TextInput::make('link_url')
                ->label(__('URL tujuan'))
                ->url()
                ->maxLength(2048)
                ->helperText(__('Opsional. Untuk tautan internal, gunakan URL canonical seperti /flowerdecorationscatalog.')),
            TextInput::make('link_id')
                ->label(__('ID tujuan'))
                ->numeric()
                ->minValue(1),
            TextInput::make('position')
                ->label(__('Urutan tampil'))
                ->numeric()
                ->integer()
                ->minValue(0)
                ->default(0)
                ->required(),
            Toggle::make('is_active')
                ->label(__('Tampilkan di beranda'))
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_url')
                    ->label(__('Gambar'))
                    ->getStateUsing(fn (\App\Models\Home\HeroBanner $record): string => $record->publicImageUrl())
                    ->square(),
                TextColumn::make('title')
                    ->label(__('Judul'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('link_type')
                    ->label(__('Tautan'))
                    ->badge(),
                TextColumn::make('position')
                    ->label(__('Urutan'))
                    ->sortable(),
                ToggleColumn::make('is_active')
                    ->label(__('Aktif'))
                    ->disabled(fn (): bool => ! AdminAccess::allows('manage_customizations')),
            ])
            ->reorderable('position')
            ->defaultSort('position')
            ->actions([
                \Filament\Tables\Actions\EditAction::make(),
                \Filament\Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                \Filament\Tables\Actions\BulkActionGroup::make([
                    \Filament\Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('position')->orderBy('id');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageHeroBanners::route('/'),
        ];
    }
}
