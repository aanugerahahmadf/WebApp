{{--
    PENANDA SCOPE HALAMAN -- render hook `PanelsRenderHook::PAGE_END`
    (WelcomePanelProvider; panel storefront yang menampung seluruh
    halaman User + Welcome hasil peleburan panel).

    Satu baris, nol CSS, nol form: hanya menempelkan key scope
    halaman ini pada <body>-nya. CSS kustom dari PANEL ADMIN
    (PageStyles::styles() / TextStyles::stylesheet(), dipancarkan lewat
    hook `panels::styles.after` yang sama di ketiga panel) menarget
    penanda ini lewat selektor

        body:has([data-page-style-mount="<scope>"]) ...

    -- jadi SETIAP halaman panel ini (semua resource, semua Livewire
    pages) bisa dikustomisasi dari Admin, dan kustom halaman A tidak
    pernah mengenai halaman B karena key scope-nya berbeda.
--}}
@php
    $scopeMarker = \App\Support\PageStyles\PageStyles::scopeFromRequest();
@endphp

<div data-page-style-mount="{{ $scopeMarker }}" hidden></div>
