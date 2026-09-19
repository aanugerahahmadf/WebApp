<?php

namespace App\Filament\Admin\Resources\HelpResource;

use App\Filament\Admin\Resources\HelpResource\Pages;
use App\Models\Help\Help;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class HelpResource extends Resource
{
    protected static ?string $model = Help::class;

    protected static ?string $slug = 'helps';

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

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
        return __('Pusat Bantuan (Help)');
    }

    public static function getPluralLabel(): ?string
    {
        return __('Pusat Bantuan (Help)');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Informasi Pusat Bantuan'))
                    ->description(__('Kelola judul utama dan sub-judul yang ditampilkan di menu Help Center.'))
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->label(__('Judul Utama'))
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('subtitle')
                            ->label(__('Sub-judul'))
                            ->required()
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Forms\Components\Section::make(__('Opsi Kontak'))
                    ->description(__('Tambahkan daftar opsi kontak yang dapat dihubungi pelanggan (misal: WhatsApp, Email, Telepon).'))
                    ->schema([
                        Forms\Components\Repeater::make('contact_options')
                            ->label(__('Pilihan Kontak'))
                            ->schema([
                                Forms\Components\TextInput::make('label')
                                    ->label(__('Nama Kontak / Label'))
                                    ->placeholder('e.g. WhatsApp Support')
                                    ->required(),
                                Forms\Components\TextInput::make('subLabel')
                                    ->label(__('Keterangan / Nilai'))
                                    ->placeholder('e.g. +62 812-3456-7890')
                                    ->required(),
                                Forms\Components\TextInput::make('url')
                                    ->label(__('Link URL (Tujuan Klik)'))
                                    ->placeholder('e.g. https://wa.me/... atau mailto:...')
                                    ->required(),
                                Forms\Components\Select::make('icon')
                                    ->label(__('Ikon'))
                                    ->options([
                                        'whatsapp' => 'WhatsApp (Message Circle)',
                                        'mail' => 'Email (Mail)',
                                        'phone' => 'Phone (Call)',
                                    ])
                                    ->default('whatsapp')
                                    ->required(),
                            ])
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? __('Opsi Kontak Baru'))
                            ->grid(1)
                            ->reorderableWithButtons()
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make(__('Daftar FAQ'))
                    ->description(__('Kelola daftar tanya-jawab (Frequently Asked Questions) untuk membantu pelanggan secara cepat.'))
                    ->schema([
                        Forms\Components\Repeater::make('faqs')
                            ->label(__('Pertanyaan & Jawaban'))
                            ->schema([
                                Forms\Components\TextInput::make('question')
                                    ->label(__('Pertanyaan'))
                                    ->required(),
                                Forms\Components\Textarea::make('answer')
                                    ->label(__('Jawaban'))
                                    ->required()
                                    ->rows(3),
                            ])
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['question'] ?? __('FAQ Baru'))
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
                    ->label(__('Judul Utama'))
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-question-mark-circle'),
                Tables\Columns\TextColumn::make('subtitle')
                    ->label(__('Sub-judul'))
                    ->searchable()
                    ->limit(50),
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
                            ->title(__('Pusat Bantuan diperbarui'))
                            ->body(__('Pusat Bantuan telah berhasil diperbarui.'))
                    ),
                Tables\Actions\DeleteAction::make()
                    ->button()
                    ->color('danger')
                    ->size('lg')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('Pusat Bantuan dihapus'))
                            ->body(__('Pusat Bantuan telah berhasil dihapus.'))
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
            'index' => Pages\ManageHelps\ManageHelps::route('/'),
        ];
    }
}
