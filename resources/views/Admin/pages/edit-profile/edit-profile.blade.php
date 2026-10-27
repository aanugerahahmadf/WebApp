{{--
    Halaman profil Admin.

    Pembungkus `.profile-glass-page edit-profile-page` WAJIB ada:
    panel-glass.css memakai kelas itu untuk memberi section form bahan
    kaca yang SAMA dengan panel User -- latar --fi-glass-bg + tint,
    border --fi-glass-ring, tanpa bayangan, dan backdrop blur. Tanpa
    pembungkus ini, section jatuh ke aturan generik `.fi-section` yang
    tidak memakai blur, jadi kartunya terlihat pekat dan berbeda dari
    halaman profil User.

    `space-y-6` menjaga jarak antar-blok tetap seperti sebelumnya (dulu
    tiap Livewire component adalah anak langsung <x-filament-panels::page>).
    Kalau nanti ada komponen profil kedua, jaraknya otomatis ikut.
--}}
<x-filament-panels::page>
    <div class="profile-glass-page edit-profile-page space-y-6">
        @foreach ($this->getRegisteredCustomProfileComponents() as $component)
            @unless(is_null($component))
                @livewire($component, [], key($component))
            @endunless
        @endforeach
    </div>
</x-filament-panels::page>
