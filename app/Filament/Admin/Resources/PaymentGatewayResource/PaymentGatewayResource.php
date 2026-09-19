<?php

namespace App\Filament\Admin\Resources\PaymentGatewayResource;

use App\Filament\Admin\Resources\PaymentGatewayResource\Pages;
use App\Models\PaymentGateway\PaymentGateway;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PaymentGatewayResource extends Resource
{
    protected static ?string $model = PaymentGateway::class;

    protected static ?string $slug = 'payment-gateways';

    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?int $navigationSort = 4;

    public static function getModelLabel(): string
    {
        return __('Gateway Pembayaran');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Gateway Pembayaran');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Transaksi');
    }

    public static function getNavigationLabel(): string
    {
        return __('Gateway Pembayaran');
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return __('Total Gateway Pembayaran');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make(__('Informasi Gateway'))
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label(__('Nama'))
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('code')
                            ->label(__('Kode'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(50),
                        Forms\Components\Textarea::make('description')
                            ->label(__('Deskripsi'))
                            ->rows(3),
                        Forms\Components\KeyValue::make('config')
                            ->label(__('Konfigurasi'))
                            ->helperText(__('Konfigurasi gateway (API key, secret, dll)'))
                            ->addActionLabel(__('Tambah Konfigurasi')),
                        Forms\Components\Toggle::make('is_active')
                            ->label(__('Aktif'))
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([5])
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->label(__('Nama')),
                Tables\Columns\TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->label(__('Kode')),
                Tables\Columns\IconColumn::make('is_active')
                    ->label(__('Aktif'))
                    ->boolean()
                    ->sortable()
                    ->alignment('center'),
                Tables\Columns\TextColumn::make('created_at')
                    ->label(__('Dibuat Pada'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->slideOver()
                    ->button()
                    ->color('warning')
                    ->size('lg')
                    ->successNotification(
                        Notification::make()
                            ->success()
                            ->title(__('Gateway diperbarui'))
                            ->body(__('Gateway pembayaran berhasil diperbarui.'))
                    ),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManagePaymentGateways\ManagePaymentGateways::route('/'),
        ];
    }
}
