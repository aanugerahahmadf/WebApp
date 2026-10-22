<?php

namespace App\Filament\Admin\Pages\Home;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Support\AppIcon\AppIcon;
use Filament\Pages\Dashboard as BaseDashboard;

/**
 * Dashboard panel admin.
 *
 * Isinya widget-widget yang didaftarkan di `AdminPanelProvider`
 * (`->widgets([...])`), jadi halaman ini tidak perlu daftar widget sendiri.
 *
 * PENTING: harus `extends Filament\Pages\Dashboard`, bukan `Filament\Pages\Page`
 * biasa. View dashboard memanggil `$this->getColumns()`,
 * `$this->getWidgetData()`, dan `$this->getVisibleWidgets()` -- tiga method
 * itu milik `Filament\Pages\Dashboard`. Kalau cuma `Page`, halaman ini 500
 * dengan "Method ...::getColumns does not exist".
 *
 * `routePath` juga wajib: default `Dashboard::$routePath` = '/', jadi tanpa
 * ini dashboard pindah ke `/admin` dan `/admin/home` (dipakai
 * `StoreProgressRoadmapTest`) jadi 404.
 */
class Home extends BaseDashboard
{
    use HasDynamicBreadcrumbs;

    protected static string $routePath = 'home';

    protected static string $view = 'filament-panels::pages.dashboard';

    protected static ?string $navigationIcon = AppIcon::HOME;

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Beranda';

    public function getTitle(): string
    {
        return __('Beranda');
    }

    public static function getNavigationLabel(): string
    {
        return __('Beranda');
    }

    /**
     * Group navigasi dashboard ini.
     *
     * WAJIB `__('Beranda')` -- yaitu label SETELAH terjemahan, bukan
     * string mentah "Beranda".
     *
     * Kenapa: Filament mencocokkan group halaman dengan group yang
     * didaftarkan di `navigationGroups()` memakai label SETELAH terjemahan.
     * Provider mendaftarkan `__('Pengaturan Sistem')` -> "System Settings"
     * di locale Inggris. Kalau halaman ini mengembalikan string mentah
     * "Beranda", pencocokkan gagal, Filament membuat GROUP LIAR sendiri --
     * group tanpa nama yang tidak pernah dikontrol siapa pun, dan navigasi
     * jadi punya dua group "Beranda".
     */
    public static function getNavigationGroup(): ?string
    {
        return __('Beranda');
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            $this->getTitle(),
        ];
    }
}
