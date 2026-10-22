<?php

namespace App\Filament\Admin\Resources\DatabaseStudioResource;

use App\Filament\Shared\Concerns\HasModelColumns\HasModelColumns;
use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Models\DatabaseStudio\Migration;
use App\Support\AppIcon\AppIcon;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class DatabaseStudioResource extends Resource
{
    use HasModelColumns;
    use HasStandardActions;

    protected static ?string $model = \App\Models\DatabaseStudio\Migration::class;

    protected static ?string $slug = 'database-studio';

    protected static ?string $navigationIcon = AppIcon::DATABASE;

    protected static ?int $navigationSort = 205;

    public static function getNavigationLabel(): string
    {
        return __('Studio Basis Data');
    }

    public static function getModelLabel(): string
    {
        return __('Studio Basis Data');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Studio Basis Data');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Pengaturan Sistem');
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
            ->columns(static::tableColumns())
            ->actions([
                ...static::standardTableActions(),

                // Unduh berkas migration-nya (jika masih ada di disk).
                Action::make('download_file')
                    ->label(__('Unduh Berkas'))
                    ->icon('/images/icons/download.svg')
                    ->color('success')
                    ->visible(fn (Migration $record): bool => $record->fileExists())
                    ->action(function (Migration $record): void {
                        $path = $record->getFilePath();

                        if ($path === null || ! is_file($path)) {
                            Notification::make()
                                ->danger()
                                ->title(__('Berkas Tidak Ditemukan'))
                                ->send();

                            return;
                        }

                        response()->download($path, basename($path));
                    }),
            ])
            ->bulkActions(static::standardBulkActions())
            ->paginated(false)
            ->defaultSort('id');
    }

    /**
     * Kolom dengan label yang bisa dibaca ("Nama Migration", bukan
     * `migration`).
     *
     * @return array<int, TextColumn>
     */
    public static function tableColumns(): array
    {
        return [
            TextColumn::make('id')->label('ID')->sortable(),
            TextColumn::make('migration')->label(__('Nama Migration'))->searchable()->wrap(),
            TextColumn::make('batch')->label(__('Batch'))->sortable(),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDatabaseStudio\ListDatabaseStudio::route('/'),
            'create' => Pages\CreateDatabaseStudio\CreateDatabaseStudio::route('/create'),
            'view' => Pages\ViewDatabaseStudio\ViewDatabaseStudio::route('/{record}'),
        ];
    }
}
