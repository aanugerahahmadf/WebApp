<?php

namespace App\Filament\Admin\Resources\WeddingDecorationPolicyResource;

use App\Filament\Shared\Concerns\HasModelColumns\HasModelColumns;
use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Support\AppIcon\AppIcon;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class WeddingDecorationPolicyResource extends Resource
{
    use HasModelColumns;
    use HasStandardActions;

    protected static ?string $model = \App\Models\WeddingDecorationPolicy\WeddingDecorationPolicy::class;

    protected static ?string $slug = 'wedding-decoration-policies';

    protected static ?string $navigationIcon = AppIcon::WEDDING_POLICY;

    protected static ?int $navigationSort = 103;

    public static function getNavigationLabel(): string
    {
        return __('Kebijakan Aplikasi');
    }

    public static function getModelLabel(): string
    {
        return __('Kebijakan Aplikasi');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Kebijakan Aplikasi');
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
            'index' => Pages\ManageWeddingDecorationPolicies\ManageWeddingDecorationPolicies::route('/'),
        ];
    }
}
