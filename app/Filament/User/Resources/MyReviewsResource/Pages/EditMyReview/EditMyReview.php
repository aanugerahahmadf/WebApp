<?php

namespace App\Filament\User\Resources\MyReviewsResource\Pages\EditMyReview;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\User\Resources\MyReviewsResource\MyReviewsResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditMyReview extends EditRecord
{
    use HasDynamicBreadcrumbs;

    protected static string $resource = MyReviewsResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\ViewAction::make()
                ->label(__('Lihat'))
                ->icon('/images/icons/eye.svg'),
            Actions\DeleteAction::make()
                ->label(__('Hapus'))
                ->icon('/images/icons/trash.svg'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $photos = collect($data['photos'] ?? [])
            ->pluck('photo')
            ->filter()
            ->values()
            ->all();

        $data['photos'] = $photos;
        if (! empty($photos)) {
            $data['photo'] = $photos[0];
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
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
