<?php

namespace App\Filament\User\Widgets\PromoBannerWidget;

use App\Models\Home\HeroBanner;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class PromoBannerWidget extends Widget
{
    protected static string $view = 'User.widgets.promo-banner-widget.promo-banner-widget';

    protected static ?int $sort = 0;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function getBanners(): Collection
    {
        return HeroBanner::query()->visible()->get();
    }
}
