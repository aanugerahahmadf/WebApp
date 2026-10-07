<?php

namespace App\Filament\Welcome\Pages\Home;

use App\Filament\User\Pages\Home\Home as UserHome;
use App\Filament\Welcome\Widgets\CombinedCatalogWidget\CombinedCatalogWidget;
use App\Filament\Welcome\Widgets\ShortcutStats\ShortcutStats;
use App\Filament\Welcome\Widgets\StatsOverview\StatsOverview;
use Filament\Pages\Dashboard as BaseDashboard;

class Home extends BaseDashboard
{
    protected static string $routePath = 'home';

    protected static ?string $navigationIcon = 'heroicon-s-home';

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
     * Beranda storefront milik tamu. User yang sudah Sign In -- termasuk
     * super_admin yang juga punya panel admin -- mendarat di home panel user,
     * bukan berhenti di storefront dan mencari-cari menu akunnya.
     *
     * Dipakai mount() Livewire, bukan middleware, karena halaman katalog /
     * detail harus tetap bisa dibuka user yang sudah login
     * (Browse-from-storefront-after-SignIn). Kalau sampai ke middleware,
     * seluruh panel welcome tertutup untuk mereka.
     *
     * Hanya `/welcome/home` yang disentuh; `/welcome` dan `/` ikut karena
     * keduanya sudah diarahkan ke sini oleh RedirectToHomeController.
     *
     * `url.intended` tidak ditulis di sini, jadi tujuan setelah login yang
     * disimpan middleware tetap utuh untuk tamu yang Galilee.
     */
    public function mount(): void
    {
        if (auth()->check()) {
            // `panel: 'user'` itu wajib, bukan opsional. Page::getUrl() menulis
            // route name dari getCurrentPanel() kalau argumennya null, dan saat
            // halaman ini dirender current panel adalah 'welcome' -- sehingga
            // UserHome::getUrl() tanpa panel menghasilkan /welcome/home, yaitu
            // halaman ini sendiri. Akibatnya user yang sudah login diarahkan balik
            // ke tempat dia datang, tanpa henti; Laravel membatalkan karena lebih
            // dari 5 hop dan test gagal dengan "5 is less than 5".
            $this->redirect(UserHome::getUrl(panel: 'user'), navigate: false);
        }
    }

    public function getWidgets(): array
    {
        // StatsOverview aman untuk guest (angka 0 + sapaan "Tamu"),
        // jadi selalu ditampilkan di storefront.
        return [
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
