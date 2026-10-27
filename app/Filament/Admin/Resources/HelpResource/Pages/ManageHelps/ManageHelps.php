<?php

namespace App\Filament\Admin\Resources\HelpResource\Pages\ManageHelps;

use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Admin\Resources\HelpResource\HelpResource;
use Filament\Resources\Pages\ManageRecords;

class ManageHelps extends \Filament\Resources\Pages\ManageRecords
{
    use HasStandardActions;



    protected static string $resource = HelpResource::class;

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
