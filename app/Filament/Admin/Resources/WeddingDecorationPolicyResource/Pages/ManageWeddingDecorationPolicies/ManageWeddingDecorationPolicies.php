<?php

namespace App\Filament\Admin\Resources\WeddingDecorationPolicyResource\Pages\ManageWeddingDecorationPolicies;

use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Admin\Resources\WeddingDecorationPolicyResource\WeddingDecorationPolicyResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Resources\Pages\ManageRecords;

class ManageWeddingDecorationPolicies extends \Filament\Resources\Pages\ManageRecords
{
    use HasStandardActions;

    use HasDynamicBreadcrumbs;


    protected static string $resource = WeddingDecorationPolicyResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            WeddingDecorationPolicyResource::getUrl('index') => WeddingDecorationPolicyResource::getNavigationLabel(),
        ];
    }
}
