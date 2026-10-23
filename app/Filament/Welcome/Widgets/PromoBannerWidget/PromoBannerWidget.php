<?php

namespace App\Filament\Welcome\Widgets\PromoBannerWidget;

use App\Models\Home\HeroBanner;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Collection;

class PromoBannerWidget extends Widget
{
    protected static string $view = 'Welcome.widgets.promo-banner-widget.promo-banner-widget';

    protected static ?int $sort = 0;

    // Load together with the storefront page. This widget is small and public;
    // lazy loading made the banner request fail independently from the rest of
    // the home page when the browser session had a stale Livewire snapshot.
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public function getBanners(): Collection
    {
        return HeroBanner::query()->visible()->get();
    }
}
