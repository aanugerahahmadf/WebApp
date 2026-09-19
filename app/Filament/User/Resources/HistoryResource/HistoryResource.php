<?php

namespace App\Filament\User\Resources\HistoryResource;

use App\Filament\User\Resources\HistoryResource\Pages\ListHistories\ListHistories;
use App\Filament\User\Resources\PackageResource\PackageResource;
use App\Models\History\History;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Infolists\Components\Grid;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\TextEntry\TextEntrySize;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class HistoryResource extends Resource
{
    protected static ?string $model = History::class;

    protected static ?string $slug = 'histories';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    public static function getGloballySearchableAttributes(): array
    {
        return ['reference_number', 'type', 'info', 'notes', 'amount'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->reference_number ?? __('Riwayat');
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            __('Tipe') => $record->type ?? '-',
            __('Jumlah') => $record->amount ? 'Rp '.number_format($record->amount, 0, ',', '.') : '-',
        ];
    }

    public static function getGlobalSearchResultUrl(Model $record): ?string
    {
        return static::getUrl('index');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Transaksi & Aktivitas');
    }

    public static function getNavigationLabel(): string
    {
        return __('Histori Transaksi');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Histori Transaksi');
    }

    public static function getModelLabel(): string
    {
        return __('Histori Transaksi');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return static::getNavigationLabel();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', Filament::auth()->id())
            ->latest();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->emptyStateHeading(__('Belum ada histori transaksi'))
            ->emptyStateDescription(__('Temukan layanan pernikahan impianmu dan mulai transaksi pertama hari ini!'))
            ->emptyStateIcon('heroicon-o-clock')
            ->emptyStateActions([
                Tables\Actions\Action::make('explore')
                    ->label(__('Cari Layanan'))
                    ->url(PackageResource::getUrl())
                    ->button()
                    ->color('primary')
                    ->size('lg')
                    ->icon('heroicon-m-sparkles'),
            ])
            ->actionsAlignment('center')
            ->defaultSort('created_at', 'desc')
            ->contentGrid([
                'default' => 2,
                'md' => 2,
                'lg' => 3,
                'xl' => 4,
            ])
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    // Header Area with Icon & Type
                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\TextColumn::make('type')
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'order' => __('Pembelian'),
                                default => ucfirst($state)
                            })
                            ->badge()
                            ->color(fn ($record) => match ($record->type) {
                                'order' => 'primary',
                                default => 'gray'
                            })
                            ->icon(fn ($record) => match ($record->type) {
                                'order' => 'heroicon-m-shopping-bag',
                                default => 'heroicon-m-clock'
                            }),

                        Tables\Columns\TextColumn::make('status')
                            ->badge()
                            ->formatStateUsing(fn ($state) => match ($state) {
                                'pending' => __('Menunggu'),
                                'success', 'completed', 'approved', 'paid', 'confirmed' => __('Berhasil'),
                                'failed', 'rejected', 'expired' => __('Gagal'),
                                'cancelled' => __('Dibatalkan'),
                                default => ucfirst($state),
                            })
                            ->color(fn ($state) => match ($state) {
                                'pending' => 'warning',
                                'success', 'completed', 'approved', 'paid', 'confirmed' => 'success',
                                'failed', 'rejected', 'expired' => 'danger',
                                'cancelled' => 'danger',
                                default => 'gray',
                            })
                            ->alignEnd(),
                    ]),

                    // Main Amount Area
                    Tables\Columns\Layout\Stack::make([
                        Tables\Columns\TextColumn::make('amount')
                            ->formatStateUsing(fn ($state, $record) => '- Rp '.number_format((float) $state, 0, ',', '.'))
                            ->weight('black')
                            ->size('xl')
                            ->color('danger')
                            ->extraAttributes(['class' => 'mt-4 mb-1']),

                        Tables\Columns\TextColumn::make('info')
                            ->formatStateUsing(fn ($state) => __($state))
                            ->size('xs')
                            ->color('gray')
                            ->lineClamp(2)
                            ->extraAttributes(['class' => 'opacity-80']),
                    ]),

                    // Footer with ID and Time
                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\TextColumn::make('reference_number')
                            ->prefix('#')
                            ->size('xs')
                            ->color('gray')
                            ->weight('medium'),

                        Tables\Columns\TextColumn::make('created_at')
                            ->dateTime('d M Y, H:i')
                            ->size('xs')
                            ->color('gray')
                            ->alignEnd(),
                    ])->extraAttributes(['class' => 'mt-4 pt-3']),
                ])->extraAttributes([
                    'class' => 'bg-white dark:bg-gray-900 rounded-2xl p-5 shadow-sm hover:shadow-xl transition-all duration-300 border border-gray-100 dark:border-gray-800',
                ]),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->searchable()
                    ->options([
                        'order' => __('Pembelian'),
                    ])
                    ->label(__('Filter Tipe'))
                    ->native(false)
                    ->preload(),
                Tables\Filters\Filter::make('id')
                    ->form([
                        Forms\Components\TextInput::make('value')
                            ->label(__('ID')),
                    ])
                    ->query(fn (Builder $query, array $data) => $query->when($data['value'], fn ($q, $id) => $q->where('id', $id)))
                    ->hidden(),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->filtersTriggerAction(
                fn (Tables\Actions\Action $action) => $action
                    ->icon('heroicon-m-funnel')
                    ->label(__('Filter'))
                    ->color(fn ($livewire) => count($livewire->getTable()->getFilterIndicators()) > 0 ? 'primary' : 'gray')
                    ->badge(fn ($livewire) => count($livewire->getTable()->getFilterIndicators()) > 0 ? count($livewire->getTable()->getFilterIndicators()) : null)
            )
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->label(__('Rincian'))
                    ->button()
                    ->size('sm')
                    ->extraAttributes(['class' => 'flex-1 justify-center rounded-lg shadow-sm font-bold'])
                    ->icon('heroicon-m-magnifying-glass')
                    ->slideOver()
                    ->modalWidth('xl')
                    ->modalHeading(__('Rincian Transaksi')),
                Tables\Actions\DeleteAction::make()
                    ->label(__('Hapus'))
                    ->button()
                    ->size('sm')
                    ->extraAttributes(['class' => 'flex-1 justify-center rounded-lg shadow-sm font-bold'])
                    ->color('danger')
                    ->icon('heroicon-m-trash'),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->actionsAlignment('center')
            ->extraAttributes([
                'class' => 'filament-table-actions-container !flex !flex-row !gap-1 !p-3 !bg-gray-50/50 dark:!bg-white/5 !border-t dark:!border-gray-800',
            ]);

    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make(__('Data Transaksi'))
                    ->schema([
                        Grid::make(2)->schema([
                            TextEntry::make('reference_number')
                                ->label(__('ID Transaksi'))
                                ->weight('bold')
                                ->copyable(),
                            TextEntry::make('type')
                                ->label(__('Jenis'))
                                ->badge()
                                ->formatStateUsing(fn ($state) => match ($state) {
                                    'order' => __('Pembelian Paket'),
                                    default => ucfirst($state),
                                }),
                            TextEntry::make('status')
                                ->label(__('Status'))
                                ->badge()
                                ->formatStateUsing(fn ($state) => match ($state) {
                                    'pending' => __('Menunggu Konfirmasi'),
                                    'success', 'completed', 'approved', 'paid' => __('Selesai/Berhasil'),
                                    'failed', 'rejected', 'cancelled' => __('Gagal/Ditolak'),
                                    default => ucfirst($state),
                                })
                                ->color(fn ($state) => match ($state) {
                                    'pending' => 'warning',
                                    'success', 'completed', 'approved' => 'success',
                                    'failed', 'rejected', 'cancelled' => 'danger',
                                    default => 'gray',
                                }),
                            TextEntry::make('created_at')
                                ->label(__('Waktu Transaksi'))
                                ->dateTime('d F Y, H:i'),
                        ]),
                        TextEntry::make('amount')
                            ->label(__('Nominal'))
                            ->formatStateUsing(fn ($state, $record) => '- Rp '.number_format($state, 0, ',', '.'))
                            ->weight('black')
                            ->size(TextEntrySize::Large)
                            ->color('danger'),
                        TextEntry::make('info')
                            ->label(__('Keterangan'))
                            ->formatStateUsing(fn ($state) => __($state))
                            ->color('gray'),
                        TextEntry::make('notes')
                            ->label(__('Catatan'))
                            ->formatStateUsing(fn ($state) => __($state))

                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListHistories::route('/'),
        ];
    }
}
