<?php

namespace App\Filament\Admin\Resources\VoucherResource\Pages\ManageVouchers;

use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Admin\Resources\VoucherResource\VoucherResource;
use Filament\Resources\Pages\ManageRecords;

class ManageVouchers extends \Filament\Resources\Pages\ManageRecords
{
    use HasStandardActions;



    protected static string $resource = VoucherResource::class;

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
