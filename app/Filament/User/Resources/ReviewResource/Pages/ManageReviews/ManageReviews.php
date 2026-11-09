<?php

namespace App\Filament\User\Resources\ReviewResource\Pages\ManageReviews;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\User\Pages\Home\Home;
use App\Filament\User\Resources\ReviewResource\ReviewResource;
use App\Filament\Welcome\Resources\MyReviewsResource\MyReviewsResource;
use Filament\Resources\Pages\ManageRecords;

class ManageReviews extends ManageRecords
{
    use HasDynamicBreadcrumbs;

    protected static string $resource = ReviewResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    public function getTitle(): string
    {
        return static::$title ?? static::getResource()::getTitleCasePluralModelLabel();
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            // Panel user sudah dilebur ke Welcome, jadi sumber daya legacy ini
            // tidak punya route sendiri (`filament.welcome.resources.reviews.*`
            // tidak terdaftar). Halaman komentar milik sendiri yang benar-benar
            // hidup adalah "Komentar Penilaian Katalog Saya" (`/my-reviews`).
            MyReviewsResource::getUrl('index') => MyReviewsResource::getNavigationLabel(),
        ];
    }
}
