<?php

namespace App\Filament\Welcome\Resources\MyReviewsResource\Pages\CreateMyReview;

use App\Filament\Welcome\Resources\MyReviewsResource\MyReviewsResource;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateMyReview extends CreateRecord
{
    protected static string $resource = MyReviewsResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = Filament::auth()->id();

        $photos = collect($data['photos'] ?? [])
            ->pluck('photo')
            ->filter()
            ->values()
            ->all();

        $data['photos'] = $photos;
        $data['photo'] = $photos[0] ?? ($data['photo'] ?? null);

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
