<?php

namespace App\Support\TopNavigation;

use Closure;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Throwable;

/**
 * The one place that knows which page counts as "Home" for a Filament panel.
 *
 * AWAS: nama "chrome" di file ini berarti TIGA hal yang selalu jalan
 * bersama: topbar (logo, pencarian global, notifikasi, user menu, switcher
 * bahasa + tema), baris link navigasi di dalam topbar, dan sidebar. Ketiganya selalu punya atau selalu tidak punya bersama -- kalau
 * dipisah, satu layar bisa punya topbar tapi tidak ada cara navigasi.
 *
 * ATURAN SEKARANG: chrome tampil di SEMUA halaman, di semua panel.
 *
 * Dulu panel storefront (user, welcome) hanya menampilkan chrome di Home.
 * Aturan itu dibalik untuk aplikasi desktop (Capacitor): topbar itu tempat
 * pencarian global, user menu, switcher bahasa/tema, dan tombol kembali --
 * semuanya dibutuhkan di setiap layar, bukan cuma di Home. Panel admin
 * sebenarnya sudah begitu sejak awal.
 *
 * Yang tersisa dari aturan lama cuma prefix crumb 'Beranda' (lihat
 * needsHomeBreadcrumb()), karena crumb itu penanda posisi, bukan chrome.
 *
 * Kenapa helper, bukan literal di tiap provider:
 *
 *   WelcomePanelProvider dan UserPanelProvider sengaja dijaga sinkron
 *   (lihat docblock class-nya: "a layout change in one has to land in the
 *   other or the panels drift apart"). Closure yang ditulis terpisah di dua
 *   provider adalah dua salinan dari satu aturan, dan cara gagalnya senyap:
 *   satu panel diam-diam tetap memakai sidebar lamanya sampai ada yang
 *   menyadarinya.
 *
 * Apa yang dicakup "chrome", dan apa yang sengaja tidak:
 *
 *   shown   ->topNavigation()  baris link navigasi di dalam topbar
 *   shown   ->topbar()         topbar itu sendiri
 *   shown   sidebar            Filament hanya merendernya kalau hasNavigation();
 *                            lihat override view per panel di
 *                            resources/views/User/vendor/filament-panels/
 *                            components/sidebar/index.blade.php
 *   shown   phone bottom nav   MobileNav::shouldRender() memakai hasChrome()
 *   kept    breadcrumbs        penanda posisi, bukan chrome
 */
class TopNavigation
{
    /**
     * Panel yang chrome-nya (topbar + navigasi + sidebar) ikut berubah
     * bentuknya mengikuti halaman: topbar jadi kapsul melayang, sidebar jadi
     * drawer.
     *
     * @var list<string>
     */
    public const BREADCRUMB_HOME_PREFIX_PANELS = ['user', 'welcome'];

    /**
     * Panel yang selalu punya chrome, di halaman apa pun.
     *
     * @var list<string>
     */
    public const CHROME_ON_EVERY_PAGE = ['admin', 'user', 'welcome'];

    /**
     * The Home page class per panel id. Single source of truth -- referenced by
     * chrome gating AND by the breadcrumb root crumb.
     *
     * @var array<string, class-string<Page>>
     */
    public const PANEL_HOME_PAGES = [
        'admin' => \App\Filament\Admin\Pages\Home\Home::class,
        'user' => \App\Filament\User\Pages\Home\Home::class,
        'welcome' => \App\Filament\Welcome\Pages\Home\Home::class,
    ];

    /**
     * Whether the current page should be prefixed with a 'Beranda' breadcrumb.
     *
     * True exactly when the current panel is a BREADCRUMB_HOME_PREFIX_PANELS
     * panel and the page is not its Home. Chrome sudah ada di semua halaman,
     * jadi prefix ini bukan lifeline -- ini penanda posisi: Home
     *
     * Lives here so the breadcrumb rule and the chrome rule cannot disagree,
     * and so the two things that build breadcrumbs (HasDynamicBreadcrumbs for
     * normal pages, HasAuthBreadcrumbs for the simple-layout auth pages) ask
     * one question instead of each re-deriving the panel check.
     */
    public static function needsHomeBreadcrumb(): bool
    {
        $panelId = static::currentPanelId();

        if ($panelId === null || ! in_array($panelId, static::BREADCRUMB_HOME_PREFIX_PANELS, true)) {
            return false;
        }

        return ! static::visible($panelId);
    }

    /**
     * The closure to hand to `->topNavigation()` and `->topbar()`.
     *
     * Returns a Closure rather than a bool on purpose: Filament stores the
     * argument and evaluates it on every `hasTopNavigation()` / `hasTopbar()`
     * call, which the layout blades make at render time. A bool would freeze the
     * decision at panel-construction time -- when there is no route yet -- and
     * every page would get the same answer.
     */
    public static function onlyOnHome(): Closure
    {
        return static fn (): bool => static::visible();
    }

    /**
     * The closure to hand to `->topNavigation()` and `->topbar()` when the
     * panel shows its chrome on EVERY page.
     *
     * Ini yang dipakai panel storefront sekarang. Alasannya aplikasi desktop
     * (Capacitor): topbar itu sumber pencarian global, user menu, switcher
     * bahasa/tema, dan tombol kembali -- semuanya dibutuhkan di setiap
     * layar, bukan cuma di Home.
     */
    public static function always(): Closure
    {
        return static fn (): bool => true;
    }

    /**
     * Whether the panel being rendered has chrome on the page being rendered.
     *
     * Dipakai tiga potongan chrome yang harus satu pendapat: topbar
     * (->topbar() di provider), sidebar (override view sidebar/index), dan
     * bottom nav HP (MobileNav::shouldRender()).
     */
    public static function hasChrome(?string $panelId = null): bool
    {
        $panelId ??= static::currentPanelId();

        if ($panelId === null) {
            return false;
        }

        return in_array($panelId, static::CHROME_ON_EVERY_PAGE, true);
    }

    /**
     * Whether the page being rendered right now is the panel's Home page, i.e.
     * whether this panel should be showing its chrome.
     *
     * @param  string|null  $panelId  defaults to the panel currently rendering.
     *                                Passed through to enabled() so the Home
     *                                route name is built for THIS panel -- see the
     *                                note on routeName().
     */
    public static function visible(?string $panelId = null): bool
    {
        $panelId ??= static::currentPanelId();

        $homePage = $panelId === null ? null : (static::PANEL_HOME_PAGES[$panelId] ?? null);

        if ($homePage === null) {
            return false;
        }

        return static::enabled($homePage, $panelId);
    }

    /**
     * Whether a Home URL is known -- the breadcrumb root needs it, and it must
     * be able to answer "no Home for this panel" without throwing.
     */
    public static function homeUrl(?string $panelId = null): ?string
    {
        $panelId ??= static::currentPanelId();

        $homePage = $panelId === null ? null : (static::PANEL_HOME_PAGES[$panelId] ?? null);

        if ($homePage === null || ! is_subclass_of($homePage, Page::class)) {
            return null;
        }

        try {
            $url = $homePage::getUrl(panel: $panelId);

            return is_string($url) && $url !== '' ? $url : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Whether the current render is the given Home page.
     *
     * @param  class-string<Page>  $homePage
     * @param  string|null  $panelId  the panel $homePage belongs to. MUST be
     *                                passed whenever the caller knows it:
     *                                `getRouteName()` with no panel falls back
     *                                to `Filament::getCurrentPanel()`, so asking
     *                                "is this the User panel's Home?" while the
     *                                Welcome panel renders would build the name
     *                                `filament.welcome.pages.home` and answer
     *                                yes to the wrong question.
     */
    public static function enabled(string $homePage, ?string $panelId = null): bool
    {
        if (! is_subclass_of($homePage, Page::class)) {
            return false;
        }

        // Signal 1 -- route name. Correct for a cold page load and for SPA
        // navigation: Livewire v3's `navigate` issues a real fetch() to the
        // destination URL (js/plugins/navigate/fetch.js), so the server-side
        // route genuinely is the page being rendered.
        //
        // It is NOT correct for a Livewire component update, because those POST
        // to /livewire/update. That is what signal 2 is for.
        if (Route::is(static::routeName($homePage, $panelId))) {
            return true;
        }

        // Signal 2 -- the component actually on the stack. Covers /livewire/update,
        // where the route says nothing useful but the page being re-rendered is
        // unambiguous.
        //
        // `Livewire::current()` is the head of the component stack, i.e. the
        // page component itself and not a widget nested inside it. That matters
        // because Home renders StatsOverview, RevenueChart and friends, and
        // those must not answer on the page's behalf.
        try {
            return Livewire::current() instanceof $homePage;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * `filament.{panel}.pages.home`.
     *
     * Two attempts, most authoritative first:
     *
     *   1. `Page::getRouteName()` -- Filament's own API, so it keeps following
     *      the page if it is ever moved to another cluster or panel. It reads
     *      `Filament::getCurrentPanel()`, which is null outside a panel request
     *      (a queue job, an artisan command, a unit test), and that null then
     *      blows up on `generateRouteName()`.
     *
     *   2. `getRouteBaseName()` -- all three Home classes define it, and it
     *      returns the fully-qualified route name ('filament.user.pages.home')
     *      with no panel lookup at all, so it still answers in exactly the
     *      contexts where (1) throws.
     *
     * Both agree on the registered names (checked with `route:list`); (2) is the
     * safety net, not a second definition of the truth.
     */
    protected static function routeName(string $homePage, ?string $panelId = null): string
    {
        try {
            $name = $homePage::getRouteName($panelId);

            if (is_string($name) && $name !== '') {
                return $name;
            }
        } catch (Throwable) {
            // No current panel -- fall through to the panel-free lookup.
        }

        if (method_exists($homePage, 'getRouteBaseName')) {
            try {
                $name = $homePage::getRouteBaseName();

                if (is_string($name) && $name !== '') {
                    return $name;
                }
            } catch (Throwable) {
                // Fall through to "no route".
            }
        }

        return '';
    }

    /**
     * The panel currently rendering, or null outside a panel request.
     */
    public static function currentPanelId(): ?string
    {
        try {
            return Filament::getCurrentPanel()?->getId();
        } catch (Throwable) {
            return null;
        }
    }
}