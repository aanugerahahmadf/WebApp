<?php

namespace App\Filament\Admin\Resources\PaymentGatewayResource;

use App\Filament\Shared\Concerns\HasModelColumns\HasModelColumns;
use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Support\AppIcon\AppIcon;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentGatewayResource extends Resource
{
    use HasModelColumns;
    use HasStandardActions;

    protected static ?string $model = \App\Models\PaymentGateway\PaymentGateway::class;

    protected static ?string $slug = 'payment-gateways';

    protected static ?string $navigationIcon = AppIcon::PAYMENT_GATEWAY;

    protected static ?int $navigationSort = 303;

    public static function getNavigationLabel(): string
    {
        return __('Gateway Pembayaran');
    }

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
            'index' => Pages\ManagePaymentGateways\ManagePaymentGateways::route('/'),
        ];
    }
}
