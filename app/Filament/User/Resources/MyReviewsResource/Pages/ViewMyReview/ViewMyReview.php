<?php

namespace App\Filament\User\Resources\MyReviewsResource\Pages\ViewMyReview;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\User\Resources\MyReviewsResource\MyReviewsResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewMyReview extends ViewRecord
{
    use HasDynamicBreadcrumbs;

    protected static string $resource = MyReviewsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label(__('Ubah'))
                ->icon('/images/icons/pencil.svg'),
            Actions\DeleteAction::make()
                ->label(__('Hapus'))
                ->icon('/images/icons/trash.svg'),
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            MyReviewsResource::getUrl('index') => MyReviewsResource::getNavigationLabel(),
            $this->getTitle(),
        ];
    }
}
