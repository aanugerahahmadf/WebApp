<?php

namespace App\Filament\Admin\Resources\ReportResource\Pages\ManageReports;

use App\Filament\Admin\Resources\ReportResource\ReportResource;
use Filament\Resources\Pages\ManageRecords;

class ManageReports extends ManageRecords
{
    protected static string $resource = ReportResource::class;

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
