<?php

namespace App\Filament\Admin\Resources\TransactionResource\Pages\ListTransactions;

use App\Filament\Shared\Concerns\HasStandardActions\HasStandardActions;
use App\Filament\Admin\Resources\TransactionResource\TransactionResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use Filament\Resources\Pages\ListRecords;

class ListTransactions extends \Filament\Resources\Pages\ListRecords
{
    use HasStandardActions;

    use HasDynamicBreadcrumbs;


    protected static string $resource = TransactionResource::class;

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            TransactionResource::getUrl('index') => TransactionResource::getNavigationLabel(),
        ];
    }
}
