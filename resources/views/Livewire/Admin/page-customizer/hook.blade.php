{{--
    Mount untuk render hook `RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE`
    (AdminPanelProvider): panel kustomizer dirender TEPAT DI ATAS TABEL
    halaman LIST resource -- dan TIDAK menyuntik halaman form/edit/view
    (isi rekam beda-beda; panel di sana malah jelek).

    (Terlipat) supaya rapi: panel berada di dalam <details> tertutup, jadi
    halaman tetap bersih -- satu klik membukanya tanpa menimpa apa pun.

    Scope, kirim ke komponen: diambil dari route aktif supaya panel di
    atas /admin/catalogs mengedit scope `catalogs`, di atas /admin/users
    mengedit `users`, dst. -- TANPA konfigurasi per halaman.
--}}
@php
    $pageStyleScope = $pageStyleScope ?? \App\Support\PageStyles\PageStyles::scopeFromRequest();
@endphp

<section class="mt-6" data-page-customizer-mount data-page-style-mount="{{ $pageStyleScope }}">

    <details class="rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-white/5 p-4">
        <summary class="flex cursor-pointer select-none items-center justify-between gap-2">
            <span class="flex items-center gap-2 text-sm font-semibold text-gray-900 dark:text-white">
                <img src="/images/icons/brush.svg" class="h-4 w-4 shrink-0" alt="" />
                {{ __('Kustomisasi Tabel :table', ['table' => \Illuminate\Support\Str::headline($pageStyleScope)]) }}
            </span>
            <span class="hidden sm:block text-xs text-gray-500 dark:text-gray-400">
                {{ __('Pengaturan khusus HALAMAN INI -- tidak menyentuh halaman lain') }}
            </span>
        </summary>

        <div class="mt-4">
            @livewire('fm-admin-page-customizer', ['scope' => $pageStyleScope], key('page-customizer-'.$pageStyleScope))
        </div>
    </details>
</section>
