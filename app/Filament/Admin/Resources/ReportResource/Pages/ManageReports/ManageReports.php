<?php

namespace App\Filament\Admin\Resources\ReportResource\Pages\ManageReports;

use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Admin\Resources\ReportResource\ReportResource;
use Filament\Resources\Pages\ManageRecords;

class ManageReports extends \Filament\Resources\Pages\ManageRecords
{
    use HasStandardActions;



    protected static string $resource = ReportResource::class;

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
