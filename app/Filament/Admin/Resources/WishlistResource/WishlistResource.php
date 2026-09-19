<?php

namespace App\Filament\Admin\Resources\WishlistResource;

use App\Filament\Admin\Resources\WishlistResource\Pages;
use App\Models\Wishlist\Wishlist;
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
 * @property-read Wishlist $record
 */
class WishlistResource extends Resource
{
    protected static ?string $model = Wishlist::class;

    protected static ?string $slug = 'wishlists';

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?int $navigationSort = 6;

    public static function getModelLabel(): string
    {
        return __('Keinginan Pelanggan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Keinginan Pelanggan');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Transaksi');
    }

    public static function getNavigationLabel(): string
    {
        return __('Keinginan Pelanggan');
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
        return __('Total Keinginan Pelanggan');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Detail Wishlist'))
                    ->description(__('Informasi pelanggan dan paket dekorasi yang diinginkan.'))
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->searchable()
                            ->label(__('Pelanggan'))
                            ->relationship('user', 'full_name')
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('package_id')
                            ->searchable()
                            ->label(__('Paket Dekorasi'))
                            ->relationship('package', 'name')
                            ->preload()
                            ->required(),
                    ])->columns(['sm' => 2]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([5])
            ->columns([
                Tables\Columns\TextColumn::make('user.full_name')
                    ->searchable()
                    ->label(__('Pelanggan'))
                    ->sortable()
                    ->icon('heroicon-o-user'),
                Tables\Columns\TextColumn::make('package.name')
                    ->searchable()
                    ->label(__('Paket Dekorasi'))
                    ->badge()
                    ->color('info')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Ditambahkan Pada'))
                    ->dateTime()
                    ->alignment('center')
                    ->sortable()
                    ->icon('heroicon-o-calendar'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Terakhir Diubah'))
                    ->dateTime()
                    ->sortable()
                    ->alignment('center')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
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
                            ->title(__('Wishlist diperbarui'))
                            ->body(__('Data keinginan pelanggan berhasil diubah.'))
                    ),
                Tables\Actions\DeleteAction::make()
                    ->button()
                    ->color('danger')
                    ->size('lg')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('Wishlist dihapus'))
                            ->body(__('Data keinginan pelanggan berhasil dihapus.'))
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
            'index' => Pages\ManageWishlists\ManageWishlists::route('/'),
        ];
    }
}
