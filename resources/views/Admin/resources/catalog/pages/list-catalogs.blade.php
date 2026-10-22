{{--
    Halaman daftar katalog.

    TIGA tabel, satu per satu, dipilih lewat sub-navigasi SIDEBAR halaman ini
    (lihat `ListCatalogs::getSubNavigation()`):

      - `?tab=category` -> tabel kategori
      - `?tab=package`  -> tabel paket
      - (tanpa param)    -> tabel katalog

    Visibilitas dihitung SERVER-SIDE dari `$this->catalogTab()` -- bukan dari
    `request()->query()` (query param tidak ikut pada request Livewire), dan
    bukan lewat Alpine/JS: tidak ada kedipan saat berpindah, dan tetap benar
    walau JS gagal dimuat.
--}}
<x-filament-panels::page
    @class([
        'fi-resource-list-records-page',
        'fi-resource-' . str_replace('/', '-', $this->getResource()::getSlug()),
    ])
>
    <div class="flex flex-col gap-y-6">
        <x-filament-panels::resources.tabs />

        @if ($this->canViewTables())
            @if ($this->catalogTab() === 'category')
                <livewire:fm-admin-catalog-categories />
            @elseif ($this->catalogTab() === 'package')
                <livewire:fm-admin-catalog-packages />
            @endif
        @endif

        @if ($this->catalogTab() === 'catalog')
            {{ $this->table }}
        @endif
    </div>
</x-filament-panels::page>
