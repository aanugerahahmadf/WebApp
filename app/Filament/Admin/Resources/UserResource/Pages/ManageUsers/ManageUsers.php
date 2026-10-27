<?php

namespace App\Filament\Admin\Resources\UserResource\Pages\ManageUsers;

use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Admin\Resources\UserResource\UserResource;
use Filament\Resources\Pages\ManageRecords;

class ManageUsers extends \Filament\Resources\Pages\ManageRecords
{
    use HasStandardActions;



    protected static string $resource = UserResource::class;

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
