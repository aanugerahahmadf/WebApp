@props([
    'navigation',
])

@php
    $openSidebarClasses = 'fi-sidebar-open w-[var(--sidebar-width)] translate-x-0 shadow-xl ring-1 ring-gray-950/5 dark:ring-white/10 rtl:-translate-x-0';
    $isRtl = __('filament-panels::layout.direction') === 'rtl';

    // PERUBAHAN PERILAKU (2026-10-25, kedua): switcher bahasa + tema dan
    // blok profil diminta "PALING BAWAH" di sidebar, bukan sebaris dengan
    // logo. Flag ini menyalakan HANYA satu dari dua tempat itu supaya hook
    // SIDEBAR_NAV_START tidak dipanggil dua kali (switcher dobel).
    //
    // Posisi 'header' lama TIDAK dihapus -- cukup ubah jadi false untuk
    // mengembalikan switcher ke sebelah logo.
    $switchersAtSidebarBottom = true;
@endphp

    {{--
        SIDEBAR ADMIN DATAR: TIDAK ADA KAPSUL, TIDAK ADA OFFSET.

        Dulu sidebar digeser ke bawah sebesar var(--fi-capsule-stack) supaya
        tidak tertutup kapsul topbar. Topbar sekarang datar di y=0, jadi
        sidebar juga mulai di y=0 dan menyambung ke tepi layar.

        Yang membedakan hanya PERAN-nya, bukan bentuknya:
          - ponsel (< 768px): drawer penuh layar
          - tablet & desktop (>= 768px): kolom tetap di kiri
    --}}
    @if (filament()->hasTopbar())
        <style>
            .fi-panel-admin .fi-sidebar-item-label,
            .fi-panel-admin .fi-sidebar-group-label {
                font-family: 'Instrument Sans', sans-serif !important;
                font-size: 11px !important;
                font-weight: 400 !important;
                color: rgb(51 65 85) !important;
            }

            .fi-panel-admin .fi-sidebar-group-label {
                color: rgb(30 41 59) !important;
                font-size: 13px !important;
                font-weight: 500 !important;
            }

            /* Badge navigasi harus identik dengan ukuran teks item menu.
               `getNavigationBadgeColor()` tetap mengatur warna; aturan ini
               hanya menyamakan tipografi badge dengan label sidebar Admin. */
            .fi-panel-admin .fi-sidebar-item .fi-badge {
                font-family: 'Instrument Sans', sans-serif !important;
                font-size: 11px !important;
                line-height: 1rem !important;
                font-weight: 400 !important;
            }

            .fi-panel-admin .fi-sidebar-item .fi-badge * {
                font-family: 'Instrument Sans', sans-serif !important;
                font-size: 11px !important;
                line-height: 1rem !important;
                font-weight: 400 !important;
            }

            .dark .fi-panel-admin .fi-sidebar-item-label {
                color: rgb(203 213 225) !important;
            }

            .dark .fi-panel-admin .fi-sidebar-group-label {
                color: rgb(226 232 240) !important;
            }

            /* Topbar Admin memakai tipografi & warna yang sama dengan Sidebar
               Admin. Label Top Navigation tetap berukuran normal; hanya item
               dropdown yang dibuat kecil. */
            .fi-panel-admin .fi-topbar-item-label {
                font-family: 'Instrument Sans', sans-serif !important;
                font-size: 14px !important;
                font-weight: 400 !important;
                color: rgb(51 65 85) !important;
            }

            .fi-panel-admin .fi-topbar-group-label {
                font-size: 14px !important;
                font-weight: 600 !important;
                color: rgb(30 41 59) !important;
            }

            .fi-panel-admin .fi-topbar-item-button,
            .fi-panel-admin .fi-topbar-item-button .fi-topbar-item-label,
            .fi-panel-admin .fi-sidebar-item-button,
            .fi-panel-admin .fi-sidebar-item-button .fi-sidebar-item-label,
            .fi-panel-admin .fi-sidebar-group-button,
            .fi-panel-admin .fi-sidebar-group-button .fi-sidebar-group-label {
                color: rgb(51 65 85) !important;
            }

            .dark .fi-panel-admin .fi-topbar-item-label {
                color: rgb(203 213 225) !important;
            }

            .dark .fi-panel-admin .fi-topbar-group-label {
                color: rgb(226 232 240) !important;
            }

            .dark .fi-panel-admin .fi-topbar-item-button,
            .dark .fi-panel-admin .fi-topbar-item-button .fi-topbar-item-label,
            .dark .fi-panel-admin .fi-sidebar-item-button,
            .dark .fi-panel-admin .fi-sidebar-item-button .fi-sidebar-item-label,
            .dark .fi-panel-admin .fi-sidebar-group-button,
            .dark .fi-panel-admin .fi-sidebar-group-button .fi-sidebar-group-label {
                color: rgb(248 250 252) !important;
            }

            .fi-panel-admin .fi-dropdown-list-item,
            .fi-panel-admin .fi-dropdown-list-item * {
                font-family: 'Instrument Sans', sans-serif !important;
                font-size: 12px !important;
            }

            .fi-panel-admin .fi-dropdown-list-item {
                color: rgb(203 213 225) !important;
                font-weight: 400 !important;
            }

            .fi-panel-admin .fi-topbar .fi-dropdown-list-item,
            .fi-panel-admin .fi-topbar .fi-dropdown-list-item-label {
                color: rgb(51 65 85) !important;
                font-weight: 400 !important;
            }

            .dark .fi-panel-admin .fi-topbar .fi-dropdown-list-item,
            .dark .fi-panel-admin .fi-topbar .fi-dropdown-list-item-label {
                color: rgb(203 213 225) !important;
                font-weight: 400 !important;
            }

            .fi-panel-admin .fi-sidebar-item.fi-active .fi-sidebar-item-label,
            .fi-panel-admin .fi-topbar-item.fi-active .fi-topbar-item-label {
                font-weight: 500 !important;
            }

            /* Sidebar Admin: kolom penuh dari ATAS layar, tanpa kapsul.
               Tidak ada lagi cabang `@media` yang mengubah bentuk sidebar:
               topbar datar di y=0, sidebar juga mulai di y=0 dan menyambung
               ke tepi layar.

               Yang membedakan hanya PERAN-nya, bukan bentuknya:
                 - ponsel (< 768px): drawer penuh layar
                 - tablet & desktop (>= 768px): kolom tetap di kiri */
            .fi-panel-admin .fi-sidebar {
                position: fixed !important;
                top: 0 !important;
                bottom: 0 !important;
                height: auto !important;
                max-height: none !important;
                border-radius: 0 !important;
            }

            /* Tablet & desktop: sidebar adalah KOLOM, bukan drawer.
               Tombol tutup mobile (X) dan overlay gelap adalah penanda
               drawer -- keduanya disembunyikan, dan tombol ciutkan versi
               desktop ditampilkan. Filament mengunci keduanya di `lg`
               (1024px), sehingga di tablet (768-1023px) yang muncul justru
               chrome mobile. Di sini ambangnya dibuat 768px. */
            @media (min-width: 1024px) {
                .fi-panel-admin .fi-sidebar-header .fi-sidebar-mobile-close-btn {
                    display: none !important;
                }

                .fi-panel-admin .fi-sidebar-header .fi-sidebar-collapse-btn {
                    display: flex !important;
                }

                .fi-panel-admin .fi-sidebar-close-overlay {
                    display: none !important;
                }
            }

            /* Ponsel: drawer selebar layar seperti sebelumnya. */
            @media (max-width: 1023px) {
                .fi-panel-admin .fi-sidebar {
                    width: 100vw !important;
                    max-width: 100vw !important;
                }
            }

            /* ------------------------------------------------------------
               PENCARIAN GLOBAL: PEMICU DI SIDEBAR, HASIL DI MODAL (2026-10-26)

               Kotak di bawah logo bukan lagi input Livewire, melainkan
               tombol pemicu. Hasil pencarian dirender di dalam modal
               `admin-global-search` (lihat AdminPanelProvider dan
               resources/views/Admin/components/global-search-modal/
               global-search-modal.blade.php). Dengan begitu daftar hasil
               tidak lagi menimpa item navigasi sidebar.

               Tidak ada lagi aturan lebar panel hasil di sini: panelnya
               sekarang berada di dalam modal, bukan di dalam sidebar.
               ------------------------------------------------------------ */
        </style>
    @endif

{{-- format-ignore-start --}}
<aside
    x-data="{}"
    style="background-color: var(--fi-chrome-bg) !important; background-image: none !important; border: 0 !important; box-shadow: none !important; -webkit-backdrop-filter: blur(12px) saturate(160%) !important; backdrop-filter: blur(12px) saturate(160%) !important;"
    @if (filament()->isSidebarCollapsibleOnDesktop() && (! filament()->hasTopNavigation()))
        x-cloak
        x-bind:class="
            $store.sidebar.isOpen
                ? @js($openSidebarClasses . ' ' . 'lg:sticky')
                : '-translate-x-full rtl:translate-x-full lg:sticky lg:translate-x-0 rtl:lg:-translate-x-0'
        "
    @else
        @if (filament()->hasTopNavigation())
            x-cloak
            x-bind:class="$store.sidebar.isOpen ? @js($openSidebarClasses) : '-translate-x-full rtl:translate-x-full'"
        @elseif (filament()->isSidebarFullyCollapsibleOnDesktop())
            x-cloak
            x-bind:class="$store.sidebar.isOpen ? @js($openSidebarClasses . ' ' . 'lg:sticky') : '-translate-x-full rtl:translate-x-full'"
        @else
            x-cloak="-lg"
            x-bind:class="
                $store.sidebar.isOpen
                    ? @js($openSidebarClasses . ' ' . 'lg:sticky')
                    : 'w-[var(--sidebar-width)] -translate-x-full rtl:translate-x-full lg:sticky'
            "
        @endif
    @endif
    {{
        $attributes->class([
            'fi-sidebar fixed start-0 z-30 flex flex-col content-start bg-white transition-all dark:bg-gray-900 lg:z-0 lg:bg-transparent lg:shadow-none lg:ring-0 lg:transition-none dark:lg:bg-transparent',
            'lg:translate-x-0 rtl:lg:-translate-x-0' => ! (filament()->isSidebarCollapsibleOnDesktop() || filament()->isSidebarFullyCollapsibleOnDesktop() || filament()->hasTopNavigation()),
            'lg:-translate-x-full rtl:lg:translate-x-full' => filament()->hasTopNavigation(),
        ])
    }}
>
    <div class="overflow-x-clip">
        <header
            style="background-color: transparent !important; background-image: none !important; border: 0 !important; box-shadow: none !important; --tw-ring-color: transparent !important; --tw-ring-shadow: 0 0 #0000 !important; --tw-ring-offset-shadow: 0 0 #0000 !important;"
            class="fi-sidebar-header flex h-16 items-center bg-white px-6 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 lg:shadow-sm"
        >
            {{--
                BARIS LOGO (2026-10-25, kedua).

                Switcher bahasa + tema TIDAK lagi sebaris dengan logo:
                permintaan terbaru menaruhnya di DASAR sidebar, bersama blok
                profil. Posisi lama di header ini dinonaktifkan lewat flag
                $switchersAtSidebarBottom (lihat @php di atas) -- TIDAK
                dihapus, supaya mudah dikembalikan.
            --}}
            <div
                x-show="$store.sidebar.isOpen"
                x-transition:enter="lg:transition lg:delay-100"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                class="me-auto flex items-center gap-3"
            >
                @if ($homeUrl = filament()->getHomeUrl())
                    <a {{ \Filament\Support\generate_href_html($homeUrl) }}>
                        <x-filament-panels::logo />
                    </a>
                @else
                    <x-filament-panels::logo />
                @endif

                {{--
                    NONAKTIF (2026-10-25), JANGAN DIHAPUS: switcher bahasa +
                    tema pindah ke DASAR sidebar (lihat blok `fi-sidebar-bawah`
                    setelah </nav>). Markup dan hook-nya tetap di sini, hanya
                    dinonaktifkan lewat $switchersAtSidebarBottom supaya mudah
                    dikembalikan. Kalau keduanya dihidupkan sekaligus, switcher
                    muncul dua kali untuk satu pengaturan.
                --}}
                @unless ($switchersAtSidebarBottom)
                    <div class="fi-sidebar-switchers flex items-center gap-1.5">
                        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_NAV_START) }}
                    </div>
                @endunless
            </div>

            {{-- Header sekarang berisi SATU baris: logo + switcher bahasa/tema
                 di kiri, tombol kontrol (ciutkan / tutup) di kanan.

                 Alasannya: logo dan switcher tema + bahasa adalah dua kelompok
                 kontrol yang sama untuk satu orang -- identitas di kiri,
                 preferensi di sebelahnya. Ditumpuk di dua baris terpisah,
                 keduanya terbaca sebagai dua blok yang mengambang; sebaris,
                 mereka jadi satu blok utuh.

                 `me-auto` pada pembungkus logo + switcher yang mendorong
                 tombol-tombol ke kanan; `ms-auto` masing-masing tombol
                 dipertahankan supaya tetap benar walau logo disembunyikan
                 (sidebar ciut -> pembungkus itu `x-show` false, tombol
                 lebarkan tampil sendirian). --}}
            @if (filament()->isSidebarCollapsibleOnDesktop())
                <x-filament::icon-button
                    color="gray"
                    {{-- Chevron lama dinonaktifkan; kontrol sidebar memakai
                         hamburger seperti desain aplikasi. --}}
                    icon="/images/icons/menu.svg"
                    {{-- @deprecated Use `panels::sidebar.expand-button.rtl` instead of `panels::sidebar.expand-button` for RTL. --}}
                    :icon-alias="$isRtl ? ['panels::sidebar.expand-button.rtl', 'panels::sidebar.expand-button'] : 'panels::sidebar.expand-button'"
                    icon-size="lg"
                    :label="__('filament-panels::layout.actions.sidebar.expand.label')"
                    x-cloak
                    x-data="{}"
                    x-on:click="$store.sidebar.open()"
                    x-show="! $store.sidebar.isOpen"
                    class="mx-auto"
                />
            @endif

            @if (filament()->isSidebarCollapsibleOnDesktop() || filament()->isSidebarFullyCollapsibleOnDesktop())
                <x-filament::icon-button
                    color="gray"
                    {{-- Chevron lama dinonaktifkan; kontrol sidebar memakai
                         hamburger seperti desain aplikasi. --}}
                    icon="/images/icons/menu.svg"
                    {{-- @deprecated Use `panels::sidebar.collapse-button.rtl` instead of `panels::sidebar.collapse-button` for RTL. --}}
                    :icon-alias="$isRtl ? ['panels::sidebar.collapse-button.rtl', 'panels::sidebar.collapse-button'] : 'panels::sidebar.collapse-button'"
                    icon-size="lg"
                    :label="__('filament-panels::layout.actions.sidebar.collapse.label')"
                    x-cloak
                    x-data="{}"
                    x-on:click="$store.sidebar.close()"
                    x-show="$store.sidebar.isOpen"
                    class="fi-sidebar-collapse-btn ms-auto hidden lg:flex"
                />
            @endif

            {{-- Mobile close button: always shown on small screens regardless of collapsible config --}}
            <x-filament::icon-button
                color="gray"
                icon="/images/icons/x.svg"
                icon-alias="panels::sidebar.mobile-close-button"
                icon-size="lg"
                :label="__('filament-panels::layout.actions.sidebar.collapse.label')"
                x-data="{}"
                x-on:click="$store.sidebar.close()"
                class="fi-sidebar-mobile-close-btn ms-auto flex lg:hidden"
            />
        </header>

        {{--
            PEMICU PENCARIAN GLOBAL -- TEPAT DI BAWAH LOGO (2026-10-26).

            Kotak ini HANYA tombol. Input dan hasil pencariannya sekarang
            berada di dalam modal `admin-global-search` (dirender dari
            AdminPanelProvider lewat hook BODY_END).

            Kenapa modal: panel hasil bawaan Filament adalah dropdown
            absolut. Di dalam sidebar ia menimpa item navigasi sehingga
            dua lapisan teks bertumpuk -- persis yang dilaporkan pemilik.
            Di modal, hasil punya ruang sendiri dan tidak menyentuh
            navigasi.

            Livewire GlobalSearch tetap komponen Filament yang sama, jadi
            debounce, pencarian per resource, dan navigasi keyboard tidak
            berubah. Hanya tempatnya yang pindah.

            Hanya dirender kalau sidebar sedang TERBUKA. Dengan
            ->sidebarFullyCollapsibleOnDesktop(), sidebar bisa menciut jadi
            rel sempit; kotak pencarian selebar kolom tidak mungkin muat di
            sana. `x-show` (bukan `x-cloak`) mengikuti pola logo di atas.
        --}}
        {{-- BARIS SWITCHER TERPINDAH KE DALAM <header> (SEKARANG NONAKTIF).

             Sebelumnya baris `fi-sidebar-switchers` berdiri sendiri di bawah
             header, lalu dipindahkan sebaris ke dalam <header>. Perubahan
             terakhir (2026-10-25): baris itu pindah LAGI ke dasar sidebar
             (blok `fi-sidebar-bawah` setelah </nav>), bersama blok profil --
             dan versi di header dinonaktifkan lewat $switchersAtSidebarBottom.

             Wrapper lama TIDAK dipanggil dua kali dengan sengaja: membiarkan
             keduanya aktif berarti hook SIDEBAR_NAV_START dirender dua kali
             dan switcher muncul dua kali untuk satu pengaturan.

             CATATAN: file ini adalah salinan view Filament. Kalau
             filament/filament di-upgrade, bandingkan dulu dengan
             vendor/filament/filament/resources/views/components/sidebar/
             index.blade.php -- jangan ditimpa buta, blok di atas adalah
             satu-satunya beda yang disengaja.
        --}}
    </div>

    <nav
        class="fi-sidebar-nav flex-grow flex flex-col gap-y-7 overflow-y-auto overflow-x-hidden px-6 py-8"
        style="scrollbar-gutter: stable"
    >
        @if (filament()->hasTenancy() && filament()->hasTenantMenu())
            <div
                @class([
                    'fi-sidebar-nav-tenant-menu-ctn',
                    '-mx-2' => ! filament()->isSidebarCollapsibleOnDesktop(),
                ])
                @if (filament()->isSidebarCollapsibleOnDesktop())
                    x-bind:class="$store.sidebar.isOpen ? '-mx-2' : '-mx-4'"
                @endif
            >
                <x-filament-panels::tenant-menu />
            </div>
        @endif

        <ul class="fi-sidebar-nav-groups -mx-2 flex flex-col gap-y-7">
            @foreach ($navigation as $group)
                <x-filament-panels::sidebar.group
                    :active="$group->isActive()"
                    :collapsible="$group->isCollapsible()"
                    :icon="$group->getIcon()"
                    :items="$group->getItems()"
                    :label="$group->getLabel()"
                    :attributes="\Filament\Support\prepare_inherited_attributes($group->getExtraSidebarAttributeBag())"
                />
            @endforeach
        </ul>

        <script>
            var collapsedGroups = JSON.parse(
                localStorage.getItem('collapsedGroups'),
            )

            if (collapsedGroups === null || collapsedGroups === 'null') {
                localStorage.setItem(
                    'collapsedGroups',
                    JSON.stringify(@js(
                        collect($navigation)
                            ->filter(fn (\Filament\Navigation\NavigationGroup $group): bool => $group->isCollapsed())
                            ->map(fn (\Filament\Navigation\NavigationGroup $group): string => $group->getLabel())
                            ->values()
                            ->all()
                    )),
                )
            }

            collapsedGroups = JSON.parse(
                localStorage.getItem('collapsedGroups'),
            )

            document
                .querySelectorAll('.fi-sidebar-group')
                .forEach((group) => {
                    if (
                        !collapsedGroups.includes(group.dataset.groupLabel)
                    ) {
                        return
                    }

                    // Alpine.js loads too slow, so attempt to hide a
                    // collapsed sidebar group earlier.
                    group.querySelector(
                        '.fi-sidebar-group-items',
                    ).style.display = 'none'
                    group
                        .querySelector('.fi-sidebar-group-collapse-button')
                        .classList.add('-rotate-180')
                })
        </script>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_NAV_END) }}
    </nav>

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_FOOTER) }}

    {{--
        BLOK DASAR SIDEBAR -- "PALING BAWAH BANGET" (2026-10-25).

        Permintaan: profil, switcher bahasa, dan switcher tema berdiri di
        ujung paling bawah sidebar, gaya Laravel Starter Kit:

          - Blok profil: avatar + NAMA + username + email, satu tombol yang
            membuka dropdown (edit profil / sign out). Trigger-nya ada di
            override resources/views/Admin/vendor/filament-panels/components/
            user-menu.blade.php.
          - Di bawahnya, baris `fi-sidebar-switchers` (PENANDA posisi yang
            dibaca tests/Feature/Shared/SwitcherPlacementTest) dengan hook
            SIDEBAR_NAV_START -- satu-satunya tempat hook ini dirender
            (versi di <header> dinonaktifkan lewat $switchersAtSidebarBottom).

        `nav` di atasnya ber-flex-grow, jadi blok ini selalu terdorong ke
        dasar -- persis seperti permintaan "paling bawah banget".

        CATATAN: file ini adalah salinan view Filament. Kalau filament/filament
        di-upgrade, bandingkan dulu dengan vendor/filament/filament/resources/
        views/components/sidebar/index.blade.php -- jangan ditimpa buta.
    --}}
    @if ($switchersAtSidebarBottom)
        <div
            x-show="$store.sidebar.isOpen"
            x-transition:enter="lg:transition lg:delay-100"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            class="fi-sidebar-bawah flex flex-col gap-3 border-t border-gray-950/5 px-6 pb-4 pt-4 dark:border-white/10"
            style="border-top: 0 !important;"
        >
            <div class="fi-sidebar-switchers flex items-center gap-1.5">
                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_NAV_START) }}

                {{-- Lonceng notifikasi database, DI SAMPING language switcher.
                     Panel Admin memindahkannya ke sidebar; lonceng di topbar
                     dinonaktifkan (lihat override topbar/index.blade.php).
                     Panel Welcome/User TIDAK ikut: lonceng di sana tetap di
                     topbar/header. --}}
                @include('Shared.components.notification-bell.notification-bell')
            </div>

            {{-- `top-start`: pemicunya di dasar sidebar, jadi dropdown harus
                 membuka ke ATAS -- `bottom-end` bawaan akan keluar layar. --}}
            <x-filament-panels::user-menu placement="top-start" />
        </div>
    @endif
</aside>
{{-- format-ignore-end --}}
