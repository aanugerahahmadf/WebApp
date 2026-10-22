<?php

namespace App\Providers\MobileBottomNav;

use App\Providers\MobileBottomNavItem\MobileBottomNavItem;
use App\Support\AppIcon\AppIcon;

use Filament\Navigation\NavigationItem;

class MobileBottomNav
{
    /** @var array<MobileBottomNavItem> */
    protected array $items = [];

    private function __construct() {}

    public static function make(): static
    {
        return app(static::class);
    }

    public function items(array $items): static
    {
        $this->items = $items;

        return $this;
    }

    public function fromNavigationItems(array $navItems): static
    {
        $this->items = array_map(function (NavigationItem $navItem) {
            // Ikon nav item bisa datang dari DATABASE (nama Heroicon lama).
            // AppIcon::named() menerjemahkannya ke SVG berwarna; yang sudah
            // path atau tidak dikenal dikembalikan apa adanya.
            $item = MobileBottomNavItem::make($navItem->getLabel())
                ->icon(AppIcon::named($navItem->getIcon()) ?? '/images/icons/dots.svg')
                ->url($navItem->getUrl() ?? '#')
                ->sort($navItem->getSort())
                ->isActive($navItem->isActive());

            if ($navItem->getActiveIcon()) {
                $item->activeIcon(AppIcon::named($navItem->getActiveIcon()));
            }

            if ($navItem->getBadge() !== null) {
                $item->badge($navItem->getBadge(), $navItem->getBadgeColor());
            }

            return $item;
        }, $navItems);

        return $this;
    }

    public function getItems(): array
    {
        return array_values(array_filter(
            $this->items,
            fn (MobileBottomNavItem $item): bool => $item->isVisible(),
        ));
    }
}
