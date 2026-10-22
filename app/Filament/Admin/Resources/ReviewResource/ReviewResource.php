<?php

namespace App\Filament\Admin\Resources\ReviewResource;

use App\Filament\Shared\Concerns\HasModelColumns\HasModelColumns;
use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Support\AppIcon\AppIcon;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ReviewResource extends Resource
{
    use HasModelColumns;
    use HasStandardActions;

    protected static ?string $model = \App\Models\Review\Review::class;

    protected static ?string $slug = 'reviews';

    protected static ?string $navigationIcon = AppIcon::REVIEW;

    protected static ?int $navigationSort = 110;

    public static function getNavigationLabel(): string
    {
        return __('Ulasan Pelanggan');
    }

    public static function getModelLabel(): string
    {
        return __('Ulasan Pelanggan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Ulasan Pelanggan');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Data Master');
    }

    public static function getGloballySearchableAttributes(): array
    {
        return static::getAllSearchableAttributes();
    }

    public static function form(Form $form): Form
    {
        return $form->schema(static::formSchema());
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                /*
                 * Kolom otomatis dari skema tabel hanya berisi `user_id` --
                 * nomor, bukan nama. Admin yang moderasi ulasan butuh tahu
                 * siapa yang menulisnya, jadi kolomnya menampilkan nama
                 * pelanggan.
                 */
                TextColumn::make('user.full_name')
                    ->label(__('Pelanggan'))
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('catalog.name')
                    ->label(__('Katalog'))
                    ->searchable()
                    ->badge(),

                TextColumn::make('rating')
                    ->label(__('Rating'))
                    ->formatStateUsing(fn (mixed $state): string => str_repeat('★', max(0, min(5, (int) $state))).' '.$state)
                    ->sortable(),

                TextColumn::make('comment')
                    ->label(__('Ulasan'))
                    ->limit(80)
                    ->wrap()
                    ->searchable(),

                TextColumn::make('helpful_count')
                    ->label(__('Berguna'))
                    ->badge()
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label(__('Dibuat'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->headerActions(static::standardTableHeaderActions())
            ->actions(static::standardTableActions())
            ->bulkActions(static::standardBulkActions())
            ->paginated(false)
            ->defaultSort('id');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageReviews\ManageReviews::route('/'),
        ];
    }
}
