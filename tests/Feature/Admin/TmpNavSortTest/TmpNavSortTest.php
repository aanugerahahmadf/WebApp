<?php

use App\Filament\Admin\Resources\AdminResource\AdminResource;
use App\Filament\Admin\Resources\CatalogResource\CatalogResource;
use App\Filament\Admin\Resources\HelpResource\HelpResource;
use App\Filament\Admin\Resources\ReviewResource\ReviewResource;
use Filament\Facades\Filament;

test('nav sort', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    foreach ([AdminResource::class, CatalogResource::class, HelpResource::class, ReviewResource::class] as $r) {
        echo $r.': sort='.var_export($r::getNavigationSort(), true).' group='.var_export($r::getNavigationGroup(), true).' label='.var_export($r::getNavigationLabel(), true).PHP_EOL;
    }
});
