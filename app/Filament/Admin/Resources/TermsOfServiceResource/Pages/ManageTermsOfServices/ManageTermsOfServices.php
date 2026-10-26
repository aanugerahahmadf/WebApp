<?php

namespace App\Filament\Admin\Resources\TermsOfServiceResource\Pages\ManageTermsOfServices;

use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Admin\Resources\TermsOfServiceResource\TermsOfServiceResource;
use Filament\Resources\Pages\ManageRecords;

class ManageTermsOfServices extends \Filament\Resources\Pages\ManageRecords
{
    use HasStandardActions;



    protected static string $resource = TermsOfServiceResource::class;

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
