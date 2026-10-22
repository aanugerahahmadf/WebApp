{{-- OVERRIDDEN dari vendor/filament/support/resources/views/components/icon.blade.php. --}}
{{--
    Penimpaan IKON BAWAAN Filament -> aset Google kita.

    Filament merender ikon internal (tombol aksi, notifikasi database,
    tombol tutup modal, pagination, dsb.) lewat komponen anonim ini.
    Sebelum ditimpa, nama ikon seperti 'heroicon-m-x-mark' jatuh ke
    `@svg()` -- yang langsung merender VEKTOR Heroicon bawaan paket
    blade-icons -- TANPA pernah melalui `FilamentIcon::resolve()`. Itu
    yang membuat ikon "masih heroicons" di beberapa tempat (panel
    notifikasi, tombol close) meskipun alias sudah didaftarkan: alias
    hanya menutup ikon yang PUNYA `icon-alias`, bukan nama literal.

    Perbaikannya satu titik: setiap nama yang lewat komponen ini
    diterjemahkan `AppIcon::named()` menjadi path aset
    `/images/icons/<file>.svg` (vektor Material Symbols Google yang
    diwarnai template). Path selalu mengandung '/', jadi komponen
    merendernya sebagai `<img>` -- bukan vektor Heroicon.

    Nama yang tidak ada di peta dikembalikan apa adanya, jadi perilaku
    yang rendah risiko. Sumber asli komponen tidak berubah selain
    baris `named()` di bawah.
--}}
@props([
    'alias' => null,
    'class' => '',
    'icon' => null,
])

@php
    $renderedIcon = $icon ?? $slot;

    if (is_string($renderedIcon)) {
        $renderedIcon = \App\Support\AppIcon\AppIcon::named($renderedIcon);
    }

    $icon = ($alias ? \Filament\Support\Facades\FilamentIcon::resolve($alias) : null) ?: $renderedIcon;
@endphp

@if ($icon instanceof \Illuminate\Contracts\Support\Htmlable)
    <span {{ $attributes->class($class) }}>
        {{ $icon }}
    </span>
@elseif (str_contains($icon, '/'))
    <img
        {{
            $attributes
                ->merge(['src' => $icon])
                ->class($class)
        }}
    />
@else
    @svg(
        $icon,
        $class,
        array_filter($attributes->getAttributes()),
    )
@endif
