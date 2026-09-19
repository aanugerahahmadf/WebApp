<?php

namespace App\Providers\MobileBottomNav;

use App\Providers\MobileBottomNavItem\MobileBottomNavItem;

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
            $item = MobileBottomNavItem::make($navItem->getLabel())
                ->icon($navItem->getIcon() ?? 'heroicon-o-ellipsis-horizontal')
                ->url($navItem->getUrl() ?? '#')
                ->sort($navItem->getSort())
                ->isActive($navItem->isActive());

            if ($navItem->getActiveIcon()) {
                $item->activeIcon($navItem->getActiveIcon());
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
