<?php

namespace App\Filament\Admin\Resources\ReferenceOptionResource;

use App\Filament\Shared\Concerns\HasModelColumns\HasModelColumns;
use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Support\AppIcon\AppIcon;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ReferenceOptionResource extends Resource
{
    use HasModelColumns;
    use HasStandardActions;

    protected static ?string $model = \App\Models\ReferenceOption\ReferenceOption::class;

    protected static ?string $slug = 'reference-options';

    protected static ?string $navigationIcon = AppIcon::REFERENCE;

    protected static ?int $navigationSort = 107;

    public static function getNavigationLabel(): string
    {
        return __('Opsi Referensi');
    }

    public static function getModelLabel(): string
    {
        return __('Opsi Referensi');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Opsi Referensi');
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
                        ->headerActions(static::standardTableHeaderActions())
            ->actions(static::standardTableActions())
            ->bulkActions(static::standardBulkActions())
            ->columns(static::tableColumns())
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
            'index' => Pages\ManageReferenceOptions\ManageReferenceOptions::route('/'),
        ];
    }
}
