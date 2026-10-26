<?php

namespace App\Filament\Admin\Resources\PaymentGatewayResource\Pages\ManagePaymentGateways;

use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Admin\Resources\PaymentGatewayResource\PaymentGatewayResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePaymentGateways extends \Filament\Resources\Pages\ManageRecords
{
    use HasStandardActions;



    protected static string $resource = PaymentGatewayResource::class;

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
