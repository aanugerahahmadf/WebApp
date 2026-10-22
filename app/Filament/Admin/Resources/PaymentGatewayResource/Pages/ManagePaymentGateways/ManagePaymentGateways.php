<?php

namespace App\Filament\Admin\Resources\PaymentGatewayResource\Pages\ManagePaymentGateways;

use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Admin\Resources\PaymentGatewayResource\PaymentGatewayResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Resources\Pages\ManageRecords;

class ManagePaymentGateways extends \Filament\Resources\Pages\ManageRecords
{
    use HasStandardActions;

    use HasDynamicBreadcrumbs;


    protected static string $resource = PaymentGatewayResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            PaymentGatewayResource::getUrl('index') => PaymentGatewayResource::getNavigationLabel(),
        ];
    }
}
