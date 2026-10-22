{{--
    Tabel PAKET global.

    Ditampilkan di halaman daftar katalog (panel admin) saat tab `package`
    aktif.

    Wrapper penanda `fm-admin-catalog-packages` sengaja ADA di view ini
    (bukan di halaman): penandanya ikut ke mana pun komponen ini dirender,
    dan test penjaganya memakainya untuk memastikan tabel ini tidak bocor ke
    halaman lain.
--}}
<div>
    @if ($canView)
        <div class="fm-admin-catalog-packages">
            <div class="fi-fi-page">
                <div class="fi-fi-page-content">
                    {{ $this->table }}
                </div>
            </div>
        </div>
    @endif

    <x-filament-actions::modals />
</div>
