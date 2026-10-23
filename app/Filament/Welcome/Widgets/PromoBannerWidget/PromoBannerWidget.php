<?php

namespace App\Filament\Welcome\Widgets\PromoBannerWidget;

use App\Models\Home\HeroBanner;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class PromoBannerWidget extends Widget
{
    protected static string $view = 'Welcome.widgets.promo-banner-widget.promo-banner-widget';

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    public function getBanners(): Collection
    {
        return HeroBanner::query()->visible()->get();
    }
}
