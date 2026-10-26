<?php

namespace App\Filament\Admin\Resources\ReviewResource\Pages\ManageReviews;

use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Admin\Resources\ReviewResource\ReviewResource;
use Filament\Resources\Pages\ManageRecords;

class ManageReviews extends \Filament\Resources\Pages\ManageRecords
{
    use HasStandardActions;



    protected static string $resource = ReviewResource::class;

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
