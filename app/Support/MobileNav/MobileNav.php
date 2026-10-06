<?php

namespace App\Support\MobileNav;

use App\Providers\MobileBottomNavItem\MobileBottomNavItem;
use App\Support\AppPlatform\AppPlatform;
use Filament\Resources\Resource;
use Illuminate\Support\Facades\Route;
use Throwable;

/**
 * Builds the phone-only bottom navigation bar for a Filament panel.
 *
 * Items are declared per panel in config/app-platform.php so each panel gets
 * exactly the destinations that make sense for it, and so pointing an entry at a
 * different Page/Resource is a one-line config edit. The heavy lifting (url
 * resolution, sorting, active state) lives here, which keeps the Blade partial
 * at resources/views/Shared/components/mobile-bottom-nav a pure renderer.
 */
class MobileNav
{
    /**
     * Path segments, relative to the panel root, that count as an "auth" screen.
     * The route-name check below already covers the routes Filament registers,
     * but a panel can add its own verification steps, so the path is a second,
     * independent signal.
     *
     * @var list<string>
     */
    protected const AUTH_PATHS = [
        'login',
        'logout',
        // Dua-duanya segment URL pendaftaran, bukan penamaan class: 'register'
        // adalah slug default Filament, 'signup' slug yang dipakai project ini
        // sebelum `->registrationRouteSlug('signup')` di-comment-kan. Karena
        // pendaftaran bisa hidup lagi dengan salah satu slug, keduanya dicatat.
        'register',
        'signup',
        'password-reset',
        'email-verification',
        'verify-email',
    ];

    /** @var array<string, array<MobileBottomNavItem>> */
    protected static array $cache = [];

    /**
     * Whether the bottom nav should be emitted for the given panel at all.
     *
     * Suppressed on auth pages so login / register / OTP / password-reset stay
     * a single centred card with no navigation chrome around them.
     */
    public static function shouldRender(?string $panelId = null): bool
    {
        if (! config('app-platform.mobile_nav.enabled', true)) {
            return false;
        }

        if (! AppPlatform::isMobile()) {
            return false;
        }

        $panelId ??= static::currentPanelId();

        if ($panelId === null || static::items($panelId) === []) {
            return false;
        }

        return ! static::onAuthPage($panelId);
    }

    /**
     * @return array<MobileBottomNavItem>
     */
    public static function items(?string $panelId = null): array
    {
        $panelId ??= static::currentPanelId();

        if ($panelId === null) {
            return [];
        }

        if (isset(static::$cache[$panelId])) {
            return static::$cache[$panelId];
        }

        $definitions = config("app-platform.mobile_nav.items.{$panelId}", []);

        if (! is_array($definitions) || $definitions === []) {
            return static::$cache[$panelId] = [];
        }

        $items = [];

        foreach (array_values($definitions) as $index => $definition) {
            if (! is_array($definition)) {
                continue;
            }

            $item = static::build($definition, $panelId, $index);

            // A config entry may target a Page/Resource the current user cannot
            // reach, or a class that no longer exists. Dropping it here keeps the
            // bar usable instead of rendering a dead link.
            if ($item instanceof MobileBottomNavItem && $item->isVisible()) {
                $items[] = $item;
            }
        }

        usort($items, fn (MobileBottomNavItem $a, MobileBottomNavItem $b): int => $a->getSort() <=> $b->getSort());

        return static::$cache[$panelId] = $items;
    }

    public static function flush(): void
    {
        static::$cache = [];
    }

    protected static function build(array $definition, string $panelId, int $index): ?MobileBottomNavItem
    {
        $url = static::resolveUrl($definition['url'] ?? null, $panelId);

        if (! is_string($url) || $url === '') {
            return null;
        }

        $match = ($definition['match'] ?? 'exact') === 'starts' ? 'starts' : 'exact';

        $item = MobileBottomNavItem::make((string) ($definition['label'] ?? ''))
            ->icon((string) ($definition['icon'] ?? 'heroicon-o-squares-2x2'))
            ->url($url)
            ->sort((int) ($definition['sort'] ?? ($index + 1)))
            ->isActive(static::isActive($url, $match));

        if (filled($definition['active_icon'] ?? null)) {
            $item->activeIcon((string) $definition['active_icon']);
        }

        if (array_key_exists('badge', $definition)) {
            $item->badge($definition['badge']);
        }

        $item->visible((bool) ($definition['visible'] ?? true));

        return $item;
    }

    /**
     * Accepts a Filament Page/Resource FQCN, an
     * `['page' => FQCN, 'params' => [...], 'name' => 'index']` array, or a
     * literal URL string.
     *
     * The panel id is passed explicitly rather than relying on
     * `Filament::getCurrentPanel()`. `getUrl()` falls back to that ambient panel,
     * and when it is null (a queue job, a test, a cached fragment) it throws and
     * every item would silently vanish from the bar.
     */
    protected static function resolveUrl(mixed $target, string $panelId): ?string
    {
        if (is_string($target) && $target !== '') {
            // Literal URL.
            if (str_starts_with($target, 'http://')
                || str_starts_with($target, 'https://')
                || str_starts_with($target, '/')) {
                return $target;
            }

            return static::urlFor($target, $panelId);
        }

        if (is_array($target) && isset($target['page']) && is_string($target['page'])) {
            return static::urlFor(
                $target['page'],
                $panelId,
                (array) ($target['params'] ?? []),
                $target['name'] ?? null,
            );
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $params
     */
    protected static function urlFor(string $class, string $panelId, array $params = [], mixed $name = null): ?string
    {
        if (! class_exists($class) || ! method_exists($class, 'getUrl')) {
            return null;
        }

        try {
            // Page::getUrl(array $parameters, bool $isAbsolute, ?string $panel)
            // Resource::getUrl(string $name, array $parameters, bool $isAbsolute, ?string $panel)
            // The panel id is the last argument in both, but the resource form
            // carries a leading page name.
            if (is_subclass_of($class, Resource::class)) {
                return $class::getUrl(
                    $name === null ? 'index' : (string) $name,
                    $params,
                    true,
                    $panelId,
                );
            }

            return $class::getUrl($params, true, $panelId);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * `exact` compares the whole path, `starts` treats the item as active for
     * every screen below it (e.g. a resource's index, create and edit pages).
     */
    protected static function isActive(string $url, string $match): \Closure
    {
        return function () use ($url, $match): bool {
            $current = url()->current();

            if ($match === 'starts') {
                return str_starts_with($current, rtrim($url, '/'));
            }

            return rtrim($current, '/') === rtrim($url, '/');
        };
    }

    /**
     * Two independent signals, because a panel may register custom
     * verification steps outside Filament's own route group:
     *
     *   1. route name -- Filament prefixes every auth route with
     *      `filament.{panelId}.auth.`
     *   2. path -- the auth screens live directly under the panel root
     */
    protected static function onAuthPage(string $panelId): bool
    {
        if (Route::is("filament.{$panelId}.auth.*")) {
            return true;
        }

        $panelPath = trim((string) (static::currentPanel()?->getPath() ?? $panelId), '/');

        if ($panelPath === '') {
            return false;
        }

        $segments = array_values(array_filter(explode('/', request()->path())));

        // Only trust the path signal when the panel root really is the first
        // segment; with a custom domain or a subdirectory install it is not.
        if (($segments[0] ?? null) !== $panelPath) {
            return false;
        }

        $first = $segments[1] ?? null;

        return $first !== null && in_array($first, static::AUTH_PATHS, strict: true);
    }

    /**
     * The panel being rendered, or null outside a panel request.
     */
    protected static function currentPanel(): ?\Filament\Panel
    {
        return \Filament\Facades\Filament::getCurrentPanel();
    }

    protected static function currentPanelId(): ?string
    {
        return static::currentPanel()?->getId();
    }
}
