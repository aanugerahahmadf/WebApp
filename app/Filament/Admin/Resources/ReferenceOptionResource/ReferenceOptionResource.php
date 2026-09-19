<?php

namespace App\Filament\Admin\Resources\ReferenceOptionResource;

use App\Filament\Admin\Resources\ReferenceOptionResource\Pages\ManageReferenceOptions\ManageReferenceOptions;
use App\Models\ReferenceOption\ReferenceOption;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ReferenceOptionResource extends Resource
{
    protected static ?string $model = ReferenceOption::class;

    protected static ?string $slug = 'reference-options';

    protected static ?string $navigationIcon = 'heroicon-o-list-bullet';

    protected static ?int $navigationSort = 8;

    public static function getNavigationGroup(): ?string
    {
        return __('Data Master');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function getLabel(): ?string
    {
        return __('Opsi Referensi');
    }

    public static function getPluralLabel(): ?string
    {
        return __('Opsi Referensi');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Informasi Opsi'))
                    ->schema([
                        Forms\Components\TextInput::make('type')
                            ->label(__('Tipe'))
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('key')
                            ->label(__('Nilai'))
                            ->required()
                            ->maxLength(100)
                            ->hint(__('Nilai internal yang tersimpan di database')),
                        Forms\Components\KeyValue::make('label')
                            ->label(__('Label Bahasa'))
                            ->keyLabel(__('Kode Bahasa'))
                            ->valueLabel(__('Teks'))
                            ->required()
                            ->addActionLabel(__('Tambah Bahasa')),
                        Forms\Components\TextInput::make('sort_order')
                            ->label(__('Urutan'))
                            ->numeric()
                            ->default(0),
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('Aktif'))
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([10, 25, 50])
            ->columns([
                Tables\Columns\TextColumn::make('type')
                    ->label(__('Tipe'))
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-tag'),
                Tables\Columns\TextColumn::make('key')
                    ->label(__('Nilai'))
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('label')
                    ->label(__('Label Inggris'))
                    ->state(fn (ReferenceOption $record): string => $record->getLabelForLocale('en'))
                    ->searchable(),
                Tables\Columns\TextColumn::make('label')
                    ->label(__('Label Indonesia'))
                    ->state(fn (ReferenceOption $record): string => $record->getLabelForLocale('id'))
                    ->searchable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Aktif'))
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_order')
                    ->label(__('Urutan'))
                    ->numeric()
                    ->sortable()
                    ->alignment('center'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Dibuat'))
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Diupdate'))
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('type', 'asc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label(__('Tipe'))
                    ->options(fn (): array => ReferenceOption::select('type')->distinct()->pluck('type', 'type')->toArray()),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label(__('Status Aktif')),
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
                            ->title(__('Opsi diperbarui'))
                            ->body(__('Opsi referensi telah berhasil diperbarui.'))
                    ),
                Tables\Actions\DeleteAction::make()
                    ->button()
                    ->color('danger')
                    ->size('lg')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('Opsi dihapus'))
                            ->body(__('Opsi referensi telah berhasil dihapus.'))
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
            'index' => ManageReferenceOptions::route('/'),
        ];
    }
}
