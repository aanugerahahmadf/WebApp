<?php

namespace App\Filament\Admin\Resources\WeddingDecorationPolicyResource;

use App\Filament\Admin\Resources\WeddingDecorationPolicyResource\Pages\ManageWeddingDecorationPolicies\ManageWeddingDecorationPolicies;
use App\Models\WeddingDecorationPolicy\WeddingDecorationPolicy;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class WeddingDecorationPolicyResource extends Resource
{
    protected static ?string $model = WeddingDecorationPolicy::class;

    protected static ?string $slug = 'wedding-decoration-policies';

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

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
        return __('Kebijakan Aplikasi');
    }

    public static function getPluralLabel(): ?string
    {
        return __('Kebijakan Aplikasi');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Informasi Dokumen'))
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label(__('Judul'))
                            ->required(),
                    ]),

                Forms\Components\Section::make(__('Konten'))
                    ->schema([
                        Forms\Components\Repeater::make('content')
                            ->label(__('Pasal / Bagian'))
                            ->schema([
                                Forms\Components\TextInput::make('heading')
                                    ->label(__('Heading / Kepala Pasal'))
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
                            ->itemLabel(fn (array $state): ?string => $state['heading'] ?? __('Section Baru'))
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
                    ->icon('heroicon-o-rectangle-stack'),
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
                            ->title(__('Kebijakan diperbarui'))
                            ->body(__('Kebijakan Aplikasi telah berhasil diperbarui.'))
                    ),
                Tables\Actions\DeleteAction::make()
                    ->button()
                    ->color('danger')
                    ->size('lg')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('Kebijakan dihapus'))
                            ->body(__('Kebijakan Aplikasi telah berhasil dihapus.'))
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
            'index' => ManageWeddingDecorationPolicies::route('/'),
        ];
    }
}
