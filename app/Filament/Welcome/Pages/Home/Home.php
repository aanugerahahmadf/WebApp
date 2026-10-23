<?php

namespace App\Filament\Welcome\Pages\Home;

use App\Filament\Welcome\Widgets\CombinedCatalogWidget\CombinedCatalogWidget;
use App\Filament\Welcome\Widgets\PromoBannerWidget\PromoBannerWidget;
use App\Filament\Welcome\Widgets\ShortcutStats\ShortcutStats;
use App\Filament\Welcome\Widgets\StatsOverview\StatsOverview;
use App\Support\AppIcon\AppIcon;
use Filament\Pages\Dashboard as BaseDashboard;

class Home extends BaseDashboard
{
    protected static string $routePath = 'home';

    protected static ?string $navigationIcon = AppIcon::HOME;

    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    public static function getRouteBaseName(): string
    {
        return 'filament.welcome.pages.home';
    }

    public static function getSlug(): string
    {
        return 'home';
    }

    public static function getNavigationGroup(): ?string
    {
        return __('Beranda');
    }

    public static function getNavigationLabel(): string
    {
        return __('Beranda');
    }

    public static function getNavigationIcon(): ?string
    {
        return static::$navigationIcon;
    }

    /**
     * Beranda SHOPEE-STYLE: satu halaman untuk tamu DAN yang sudah sign in.
     *
     * DULU mount() mengirim user yang sudah login ke home panel user dengan
     * `UserHome::getUrl(panel: 'user')`. Sesudah panel user dilebur ke panel
     * ini (lihat WelcomePanelProvider), `Filament::getPanel('user')` tidak
     * terdaftar lagi -- setiap kunjungan /home orang yang sudah sign in 500
     * dengan InvalidArgumentException sebelum halaman dirender. Redirect itu
     * DIHAPUS, dan halaman ini sengaja terbuka untuk DUA status: pintu akun
     * tamu ada di topbar (dropdown "Masuk ke Akun"), pintu akun user ada di
     * user menu bawaan Filament.
     *
     * "/" ikut karena route closure '/' di routes/web/web.php mengarahkan ke
     * sini.
     *
     * Mount Livewire sengaja TIDAK ditulis: tanpa redirect ronde, tidak ada
     * jalur yang bisa berputar balik ke /home sendiri (pembatalan 5 hop
     * Laravel + halaman 500 yang dulu terjadi karena getPanel('user')).
     */
    public function getWidgets(): array
    {
        // StatsOverview aman untuk guest (angka 0 + sapaan "Tamu"),
        // jadi selalu ditampilkan di storefront.
        return [
            PromoBannerWidget::class,
            StatsOverview::class,
            ShortcutStats::class,
            CombinedCatalogWidget::class,
        ];
    }

    public function getTitle(): string
    {
        return __('Beranda');
    }
}
