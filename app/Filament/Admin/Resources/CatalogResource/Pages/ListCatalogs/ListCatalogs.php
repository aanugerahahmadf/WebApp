<?php

namespace App\Filament\Admin\Resources\CatalogResource\Pages\ListCatalogs;

use App\Filament\Admin\Resources\CatalogResource\CatalogResource;
use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Support\AdminAccess\AdminAccess;
use App\Support\AppIcon\AppIcon;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Pages\ListRecords;
use Livewire\Attributes\Url;

class ListCatalogs extends \Filament\Resources\Pages\ListRecords
{
    use HasDynamicBreadcrumbs;

    protected static string $resource = CatalogResource::class;

    /**
     * View sendiri (bukan `filament-panels::resources.pages.list-records`).
     *
     * Alasannya: tabel mana yang tampil ditentukan tab aktif, jadi tabel
     * katalog harus bisa DISEMBUNYIKAN. View bawaan selalu merender
     * `{{ $this->table }}`.
     */
    protected static string $view = 'Admin.resources.catalog.pages.list-catalogs';

    /**
     * Tab aktif: `category`, `package`, atau `catalog`.
     *
     * PENTING: ini PROPERTI Livewire (`#[Url]`), bukan `request()->query()`.
     *
     * Panel admin memakai SPA navigation (`->spa()`), jadi klik pada sub-navigasi
     * TIDAK=request HTTP baru -- Livewire mengirim POST ke `/livewire/update`,
     * dan query param `?tab=` TIDAK ikut di sana. Kalau visibilitas panel
     * dibaca dari `request()->query()`, halaman yang sedang aktif berubah
     * TAPI tabelnya tidak: dua tabel tampil bersamaan, dan klik berikutnya
     * tidak berarti apa-apa.
     *
     * Dengan properti `#[Url]`, state-nya ikut di Livewire dan ikut berubah
     * saat navigasi -- dan URL-nya tetap bisa disalin/dibagikan.
     */
    #[Url]
    public ?string $tab = null;

    /**
     * Tab yang benar-benar dipakai (selalu salah satu dari tiga).
     */
    public function catalogTab(): string
    {
        $tab = (string) ($this->tab ?? 'catalog');

        return in_array($tab, ['category', 'package', 'catalog'], true) ? $tab : 'catalog';
    }

    /**
     * SUB-NAVIGASI (sidebar): Category, Packages, Catalog.
     *
     * Posisi `Start` (bawaan Filament untuk resource) merender item-item ini
     * sebagai KOLOM DI SAMPING konten -- itulah switcher-nya.
     *
     * Switcher CUMA milik Filament. Blade tidak punya navigasi sendiri:
     * dua switcher (satu di blade, satu di Filament) bikin sidebar punya dua
     * daftar yang isinya sama persis.
     *
     * @return array<NavigationItem>
     */
    public function getSubNavigation(): array
    {
        $currentTab = $this->catalogTab();

        return [
            NavigationItem::make(__('Kategori'))
                ->icon(AppIcon::FOLDER.'bookmark.svg')
                ->url(static::getUrl(['tab' => 'category']))
                ->isActiveWhen(fn (): bool => $currentTab === 'category'),

            NavigationItem::make(__('Paket'))
                ->icon(AppIcon::GIFT)
                ->url(static::getUrl(['tab' => 'package']))
                ->isActiveWhen(fn (): bool => $currentTab === 'package'),

            NavigationItem::make(__('Katalog'))
                ->icon(AppIcon::CATALOG)
                ->url(static::getUrl())
                ->isActiveWhen(fn (): bool => $currentTab === 'catalog'),
        ];
    }

    /**
     * Admin tanpa permission lihat katalog tidak boleh melihat tabel
     * kategori maupun paket.
     */
    public function canViewTables(): bool
    {
        return AdminAccess::allows('view_catalog');
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            CatalogResource::getUrl('index') => CatalogResource::getNavigationLabel(),
        ];
    }
}
