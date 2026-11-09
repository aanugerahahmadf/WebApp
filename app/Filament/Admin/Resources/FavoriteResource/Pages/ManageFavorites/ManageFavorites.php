<?php

namespace App\Filament\Admin\Resources\FavoriteResource\Pages\ManageFavorites;

use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Admin\Resources\FavoriteResource\FavoriteResource;
use Filament\Resources\Pages\ManageRecords;

class ManageFavorites extends \Filament\Resources\Pages\ManageRecords
{
    use HasStandardActions;



    protected static string $resource = FavoriteResource::class;

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
