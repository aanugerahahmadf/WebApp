<?php

namespace App\Filament\User\Resources\ReviewResource\Pages\ManageReviews;

use App\Filament\User\Resources\ReviewResource\ReviewResource;
use Filament\Resources\Pages\ManageRecords;

class ManageReviews extends ManageRecords
{
    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }
}
