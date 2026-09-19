<?php

namespace App\Filament\Admin\Resources\PaymentGatewayResource\Pages\ManagePaymentGateways;

use App\Filament\Admin\Resources\PaymentGatewayResource\PaymentGatewayResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePaymentGateways extends ManageRecords
{
    protected static string $resource = PaymentGatewayResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
