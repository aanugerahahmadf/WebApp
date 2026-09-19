<?php

namespace App\Filament\Admin\Resources\PrivacyPolicyResource;

use App\Filament\Admin\Resources\PrivacyPolicyResource\Pages;
use App\Models\PrivacyPolicy\PrivacyPolicy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PrivacyPolicyResource extends Resource
{
    protected static ?string $model = PrivacyPolicy::class;

    protected static ?string $slug = 'privacy-policies';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    public static function getNavigationGroup(): ?string
    {
        return __('Manajemen Legal');
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
        return __('Privacy Policy');
    }

    public static function getPluralLabel(): ?string
    {
        return __('Privacy Policy');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Informasi Kebijakan'))
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label(__('Judul Kebijakan'))
                            ->required(),
                    ]),

                Forms\Components\Section::make(__('Data & Keamanan'))
                    ->description(__('Organisasi rincian data dan perlindungan privasi yang dikelola sistem Kami secara in-house.'))
                    ->schema([
                        Forms\Components\Repeater::make('content')
                            ->label(__('Pasal / Bagian Data'))
                            ->schema([
                                Forms\Components\TextInput::make('heading')
                                    ->label(__('Heading / Nama Pasal'))
                                    ->required(),
                                Forms\Components\Textarea::make('body')
                                    ->label(__('Isi Pasal'))
                                    ->required()
                                    ->rows(4),
                                Forms\Components\Toggle::make('is_italic')
                                    ->label(__('Gunakan Tulisan Miring (Italic)'))
                                    ->default(false),
                            ])
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['heading'] ?? __('Rincian Baru'))
                            ->grid(1)
                            ->reorderableWithButtons()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([5])
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->label(__('Judul'))
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-shield-check'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label(__('Terakhir Diupdate'))
                    ->dateTime('d M Y H:i')
                    ->sortable()
                    ->alignment('center')
                    ->icon('heroicon-o-calendar'),
            ])
            ->filters([])
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
                            ->title(__('Kebijakan Privasi diperbarui'))
                            ->body(__('Kebijakan Privasi telah berhasil diperbarui.'))
                    ),
                Tables\Actions\DeleteAction::make()
                    ->button()
                    ->color('danger')
                    ->size('lg')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('Kebijakan Privasi dihapus'))
                            ->body(__('Kebijakan Privasi telah berhasil dihapus.'))
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
            'index' => Pages\ManagePrivacyPolicies\ManagePrivacyPolicies::route('/'),
        ];
    }
}
