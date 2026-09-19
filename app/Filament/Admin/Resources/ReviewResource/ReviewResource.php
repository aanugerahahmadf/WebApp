<?php

namespace App\Filament\Admin\Resources\ReviewResource;

use App\Filament\Admin\Resources\ReviewResource\Pages;
use App\Models\Review\Review;
use App\Models\User\User;
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
 * @property-read Review $record
 */
class ReviewResource extends Resource
{
    protected static ?string $model = Review::class;

    protected static ?string $slug = 'reviews';

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'comment';

    public static function getModelLabel(): string
    {
        return __('Ulasan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Ulasan');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['comment'];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Data Master');
    }

    public static function getNavigationLabel(): string
    {
        return __('Ulasan Pelanggan');
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
        return __('Total Ulasan Pelanggan');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Konteks Ulasan'))
                    ->description(__('Peserta dan layanan yang terlibat dalam ulasan ini.'))
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label(__('Pengulas'))
                            ->options(User::query()->pluck('full_name', 'id')->toArray())
                            ->searchable()
                            ->required(),

                        Forms\Components\Select::make('package_id')
                            ->label(__('Paket Target'))
                            ->relationship('package', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\Select::make('product_id')
                            ->label(__('Produk Target'))
                            ->relationship('product', 'name')
                            ->searchable()
                            ->preload(),
                    ])->columns(['sm' => 4]),

                Forms\Components\Section::make(__('Rating & Umpan Balik'))
                    ->description(__('Evaluasi pengguna dan komentar detail.'))
                    ->schema([
                        Forms\Components\Select::make('rating')
                            ->label(__('Skor Rating'))
                            ->searchable()
                            ->options([
                                1 => __('1 Bintang'),
                                2 => __('2 Bintang'),
                                3 => __('3 Bintang'),
                                4 => __('4 Bintang'),
                                5 => __('5 Bintang'),
                            ])
                            ->required(),
                        Forms\Components\Textarea::make('comment')
                            ->label(__('Komentar Pengulas'))
                            ->columnSpanFull(),
                    ])->columns(['sm' => 1]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([5])
            ->columns([
                Tables\Columns\TextColumn::make('user.full_name')
                    ->label(__('Pengulas'))
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-user'),
                Tables\Columns\TextColumn::make('item_name')
                    ->label(__('Layanan/Produk'))
                    ->getStateUsing(fn ($record) => $record->package?->name ?? $record->product?->name ?? '-')
                    ->searchable(['package.name', 'product.name'])
                    ->sortable()
                    ->badge()
                    ->color('info'),
                Tables\Columns\TextColumn::make('rating')
                    ->label(__('Rating'))
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        $state >= 5 => 'success',
                        $state >= 4 => 'info',
                        $state >= 3 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn ($state) => '⭐ '.$state.'/5')
                    ->alignment('center')
                    ->sortable(),
                Tables\Columns\TextColumn::make('comment')
                    ->label(__('Komentar Ulasan'))
                    ->searchable()
                    ->limit(50)
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Tanggal'))
                    ->dateTime()
                    ->alignment('center')
                    ->sortable()
                    ->icon('heroicon-o-calendar')
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
                            ->title(__('Ulasan diperbarui'))
                            ->body(__('Ulasan telah berhasil diperbarui.'))
                    ),
                Tables\Actions\DeleteAction::make()
                    ->button()
                    ->color('danger')
                    ->size('lg')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('Ulasan dihapus'))
                            ->body(__('Ulasan telah berhasil dihapus.'))
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
            'index' => Pages\ManageReviews\ManageReviews::route('/'),
        ];
    }
}
