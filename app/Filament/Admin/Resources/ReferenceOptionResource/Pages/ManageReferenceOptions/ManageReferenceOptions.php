<?php

namespace App\Filament\Admin\Resources\ReferenceOptionResource\Pages\ManageReferenceOptions;

use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Admin\Resources\ReferenceOptionResource\ReferenceOptionResource;
use Filament\Resources\Pages\ManageRecords;

class ManageReferenceOptions extends \Filament\Resources\Pages\ManageRecords
{
    use HasStandardActions;



    protected static string $resource = ReferenceOptionResource::class;

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
