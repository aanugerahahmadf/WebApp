<?php

namespace App\Filament\Admin\Resources\PrivacyPolicyResource\Pages\ManagePrivacyPolicies;

use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Admin\Resources\PrivacyPolicyResource\PrivacyPolicyResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePrivacyPolicies extends \Filament\Resources\Pages\ManageRecords
{
    use HasStandardActions;



    protected static string $resource = PrivacyPolicyResource::class;

        public function getBreadcrumbs(): array
    {
        return [];
    }
}
