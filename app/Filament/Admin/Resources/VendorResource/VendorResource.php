<?php

namespace App\Filament\Admin\Resources\VendorResource;

use App\Filament\Admin\Resources\VendorResource\Pages;
use App\Models\Vendor\Vendor;
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
 * @property-read Vendor $record
 */
class VendorResource extends Resource
{
    protected static ?string $model = Vendor::class;

    protected static ?string $slug = 'vendors';

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = 8;

    public static function getModelLabel(): string
    {
        return __('Vendor');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Vendor');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Data Master');
    }

    public static function getNavigationLabel(): string
    {
        return __('Vendor');
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
        return __('Total Vendor Terdaftar');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Informasi Vendor'))
                    ->description(__('Data profil vendor penyedia layanan dekorasi.'))
                    ->icon('heroicon-o-building-storefront')
                    ->schema([
                        Forms\Components\TextInput::make('store_name')
                            ->label(__('Nama Toko'))
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon('heroicon-o-tag'),
                        Forms\Components\TextInput::make('contact_person')
                            ->label(__('Nama Kontak'))
                            ->maxLength(255)
                            ->prefixIcon('heroicon-o-user'),
                        Forms\Components\TextInput::make('no_telp')
                            ->label(__('No. Telepon'))
                            ->tel()
                            ->maxLength(255)
                            ->prefixIcon('heroicon-o-phone'),
                        Forms\Components\RichEditor::make('store_description')
                            ->label(__('Deskripsi Toko'))
                            ->toolbarButtons([
                                'bold', 'italic', 'underline', 'link', 'bulletList', 'orderedList',
                            ])
                            ->columnSpanFull(),
                        Forms\Components\FileUpload::make('logo')
                            ->label(__('Logo Vendor'))
                            ->image()
                            ->imageEditor()
                            ->maxSize(2048)
                            ->columnSpanFull(),
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('Aktif'))
                            ->default(true)
                            ->onIcon('heroicon-s-check')
                            ->offIcon('heroicon-s-x-mark'),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([5])
            ->columns([
                Tables\Columns\ImageColumn::make('logo')
                    ->label(__('Logo'))
                    ->circular()
                    ->defaultImageUrl(asset('images/placeholders/image-placeholder.png'))
                    ->alignment('center')
                    ->height(40)
                    ->width(40),
                Tables\Columns\TextColumn::make('store_name')
                    ->searchable()
                    ->label(__('Nama Toko'))
                    ->sortable()
                    ->icon('heroicon-o-building-storefront'),
                Tables\Columns\TextColumn::make('contact_person')
                    ->searchable()
                    ->label(__('Nama Kontak'))
                    ->sortable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('no_telp')
                    ->label(__('No. Telepon'))
                    ->icon('heroicon-o-phone')
                    ->placeholder('-'),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Status'))
                    ->boolean()
                    ->sortable()
                    ->alignment('center'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Terdaftar Pada'))
                    ->dateTime()
                    ->sortable()
                    ->alignment('center')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label(__('Status'))
                    ->options([
                        true => __('Aktif'),
                        false => __('Tidak Aktif'),
                    ]),
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
                            ->title(__('Vendor diperbarui'))
                            ->body(__('Data vendor telah berhasil diperbarui.'))
                    ),
                Tables\Actions\DeleteAction::make()
                    ->button()
                    ->color('danger')
                    ->size('lg')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('Vendor dihapus'))
                            ->body(__('Data vendor telah berhasil dihapus.'))
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
            'index' => Pages\ManageVendors\ManageVendors::route('/'),
        ];
    }
}
