<?php

namespace App\Filament\Admin\Resources\MaintenanceResource;

use App\Filament\Shared\Concerns\HasModelColumns\HasModelColumns;
use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Support\AppIcon\AppIcon;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MaintenanceResource extends Resource
{
    use HasModelColumns;
    use HasStandardActions;

    protected static ?string $model = \App\Models\FmAppSetting\FmAppSetting::class;

    protected static ?string $slug = 'maintenance';

    protected static ?string $navigationIcon = AppIcon::MAINTENANCE;

    protected static ?int $navigationSort = 202;

    /**
     * Label ini memuat "iOS" (huruf besar I, kecil o, besar S).
     * `Str::ucwords()` milik Filament mengubahnya jadi "IOS" karena huruf
     * pertama kata dinaikkan -- judul halaman dan <title> jadi "IOS".
     * Karena label sudah berkapitalisasi benar, title-casing dimatikan.
     */
    protected static bool $hasTitleCaseModelLabel = false;

    public static function getNavigationLabel(): string
    {
        return __('Maintenance Website, Mobile Android & iOS');
    }

    public static function getModelLabel(): string
    {
        return __('Maintenance Website, Mobile Android & iOS');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Maintenance Website, Mobile Android & iOS');
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
                        ->headerActions(static::standardTableHeaderActions())
            ->actions(static::standardTableActions())
            ->bulkActions(static::standardBulkActions())
            ->columns(static::tableColumns())
            ->paginated(false)
            ->defaultSort('id');
    }

    /**
     * Label kolom yang bisa dibaca, bukan nama kolom database.
     *
     * `HasModelColumns::humanizeColumn()` hanya mengganti "_" menjadi spasi,
     * jadi tabel ini dulu tampil "id key value created at updated at" -- nama
     * kolom mentah di panel berbahasa Indonesia.
     *
     * @return array<int, TextColumn>
     */
    public static function tableColumns(): array
    {
        return [
            TextColumn::make('id')->label('ID')->sortable(),
            TextColumn::make('key')->label(__('Kunci'))->searchable(),
            TextColumn::make('value')->label(__('Nilai Setelan'))->wrap(),
            TextColumn::make('created_at')->label(__('Dibuat Pada'))->sortable(),
            TextColumn::make('updated_at')->label(__('Diperbarui Pada'))->sortable(),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageMaintenance\ManageMaintenance::route('/'),
        ];
    }
}
