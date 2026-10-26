<?php

namespace App\Filament\Admin\Resources\PaymentMethodResource\Pages\ManagePaymentMethods;

use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Admin\Resources\PaymentMethodResource\PaymentMethodResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePaymentMethods extends \Filament\Resources\Pages\ManageRecords
{
    use HasStandardActions;



    protected static string $resource = PaymentMethodResource::class;

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
