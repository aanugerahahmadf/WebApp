<?php

namespace App\Filament\Admin\Resources\DiscountResource\Pages\ManageDiscounts;

use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Admin\Resources\DiscountResource\DiscountResource;
use Filament\Resources\Pages\ManageRecords;

class ManageDiscounts extends \Filament\Resources\Pages\ManageRecords
{
    use HasStandardActions;



    protected static string $resource = DiscountResource::class;

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
