<?php

namespace App\Filament\Admin\Resources\ReportResource;

use App\Enums\ReportStatus\ReportStatus;
use App\Filament\Admin\Resources\ReportResource\Pages;
use App\Models\Report\Report;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReportResource extends Resource
{
    protected static ?string $model = Report::class;

    protected static ?string $slug = 'reports';

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'reason';

    public static function getModelLabel(): string
    {
        return __('Laporan');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Laporan');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Manajemen');
    }

    public static function getNavigationLabel(): string
    {
        return __('Laporan Pengguna');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Report::where('status', ReportStatus::OPEN)->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Jumlah laporan baru');
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Detail Laporan'))
                    ->schema([
                        Forms\Components\TextInput::make('user.full_name')
                            ->label(__('Pelapor'))
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('category')
                            ->label(__('Kategori'))
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\TextInput::make('reason')
                            ->label(__('Alasan'))
                            ->disabled()
                            ->dehydrated(false),
                        Forms\Components\Textarea::make('description')
                            ->label(__('Deskripsi'))
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        Forms\Components\Select::make('status')
                            ->label(__('Status'))
                            ->options(collect(ReportStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()])->toArray())
                            ->required(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                Tables\Columns\TextColumn::make('user.full_name')
                    ->label(__('Pelapor'))
                    ->searchable()
                    ->sortable()
                    ->icon('heroicon-o-user'),
                Tables\Columns\TextColumn::make('category')
                    ->label(__('Kategori'))
                    ->badge()
                    ->formatStateUsing(fn ($state) => match ($state) {
                        'product' => __('Produk'),
                        'package' => __('Paket'),
                        'vendor' => __('Vendor'),
                        'order' => __('Pesanan'),
                        'review' => __('Ulasan'),
                        'general' => __('Umum'),
                        default => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        'product', 'package' => 'info',
                        'vendor' => 'warning',
                        'order' => 'success',
                        'review' => 'danger',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('reason')
                    ->label(__('Alasan'))
                    ->limit(30)
                    ->searchable(),
                Tables\Columns\TextColumn::make('description')
                    ->label(__('Deskripsi'))
                    ->limit(40)
                    ->searchable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Tanggal'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(collect(ReportStatus::cases())->mapWithKeys(fn ($s) => [$s->value => $s->getLabel()])->toArray()),
                Tables\Filters\SelectFilter::make('category')
                    ->label(__('Kategori'))
                    ->options([
                        'product' => __('Produk'),
                        'package' => __('Paket'),
                        'vendor' => __('Vendor'),
                        'order' => __('Pesanan'),
                        'review' => __('Ulasan'),
                        'general' => __('Umum'),
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->slideOver()
                    ->button()
                    ->color('info'),
                Tables\Actions\EditAction::make()
                    ->slideOver()
                    ->button()
                    ->color('warning')
                    ->label(__('Ubah Status'))
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('Laporan diperbarui'))
                            ->body(__('Status laporan telah diperbarui.'))
                    ),
                Tables\Actions\DeleteAction::make()
                    ->button()
                    ->color('danger'),
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
            'index' => Pages\ManageReports\ManageReports::route('/'),
        ];
    }
}
