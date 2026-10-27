<?php

namespace App\Filament\Admin\Resources\DatabaseStudioResource\Pages\ViewDatabaseStudio;

use App\Filament\Admin\Resources\DatabaseStudioResource\DatabaseStudioResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDatabaseStudio extends \Filament\Resources\Pages\ViewRecord
{

    protected static string $resource = DatabaseStudioResource::class;

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
