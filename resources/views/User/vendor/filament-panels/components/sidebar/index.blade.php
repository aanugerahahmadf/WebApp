@props([
    'navigation',
])

@php
    $openSidebarClasses = 'fi-sidebar-open w-[var(--sidebar-width)] translate-x-0 shadow-xl ring-1 ring-gray-950/5 dark:ring-white/10 rtl:-translate-x-0';
    $isRtl = __('filament-panels::layout.direction') === 'rtl';
@endphp

{{--
    CHROME DI SEMUA HALAMAN.

    Panel Welcome dan User menampilkan topbar + baris navigasi di semua
    halaman (lihat ->topbar() / ->topNavigation() di PanelProvider
    masing-masing), jadi sidebar ikut ada di semua halaman: di desktop jadi
    kolom navigasi, di HP jadi drawer seperti biasa. Panel admin tidak
    tersentuh: chrome-nya memang sudah penuh di semua halaman.

    Breadcrumb tetap diawali 'Beranda' supaya selalu ada jalan pulang.

    PENTING: file ini dipakai oleh DUA panel. resources/views/User/vendor
    di-prepend untuk panel 'user' maupun 'welcome' (AppServiceProvider, blok
    $registerVendorViews -- cabang welcome mendaftarkan folder 'User' dengan
    sengaja). Panel admin punya salinannya sendiri di resources/views/Admin/
    vendor, jadi perubahan ini tidak menyentuh admin.

    Helper yang sama dengan topbar, jadi ketiga potongan chrome (topbar,
    sidebar, bottom nav) tidak mungkin berbeda pendapat soal halaman mana yang
    boleh punya navigasi.
--}}
@if (\App\Support\TopNavigation\TopNavigation::hasChrome())

    {{--
        OFFSET TOP: SIDEBAR DIBAWAH KAPSUL TOPBAR, BUKAN DI BELAKANGNYA.

        MASALAH: kapsul topbar menutupi bagian atas sidebar.

        Dua fakta yang bertemu di sini:

          1. Kapsul topbar `position: fixed` (resources/css/Shared/Shared.css
             blok 2), jadi keluar dari flow. Ruang kosongnya dikembalikan
             lewat `padding-top` .fi-layout (blok 5).

          2. Sidebar ini `fixed inset-y-0` + `h-screen`. `inset-y-0` berarti
             top:0 -- dihitung dari PUNCAK LAYAR, dan `position: fixed` sama
             sekali tidak memperhatikan `padding` leluhurnya. Sidebar tetap
             mulai di y=0, PERSIS DI BAWAH kapsul, sementara kapsul punya
             z-index 40 dan sidebar hanya 30. Baris paling atas sidebar
             (header + logo + switcher) hilang di balik kapsul.

        Kapan ini terlihat? Hanya ketika sidebar tampil sebagai KOLOM, bukan
        sebagai drawer -- terutama APLIKASI DESKTOP, yang jendelanya tidak
        punya minWidth sehingga bisa dikecilkan ke bawah 1024px, dan aturan
        drawer-nya dikecualikan oleh :root:not(.native-desktop).

        SOLUSINYA: `top` dan `height` geser sebesar var(--fi-capsule-stack) --
        PERSIS nilai yang sama dengan `padding-top` .fi-layout. Angkanya satu
        token di blok 1 Shared.css, jadi sidebar dijamin berhenti di garis
        yang sama dengan kapsul. Inilah yang membuat header dan sidebar
        SEIMBANG: keduanya mulai dari satu garis, bukan saling menindih.

        Kenapa ditulis sebagai blok <style> di dalam view ini, bukan di CSS
        panel:

          - CSS tidak bisa menanyakan "apakah topbar-nya sedang ada". Di panel
            tanpa chrome (Welcome/User di luar Home), topbar disembunyikan, dan
            menggeser sidebar ke bawah hanya menyisakan ruang kosong di atasnya.
            Blade bisa bertanya: filament()->hasTopbar().

          - Offset HARUS bisa dibatalkan di mode drawer. Style inline tidak
            bisa dikalahkan media query (kecuali !important, yang justru
           berat karena ikut menimpa aturan panel lain). Blok <style>
            di dalam view ini punya scope yang jelas: hanya saat sidebar ini
            benar-benar dirender.

        `@media (max-width: 1023px)` mengembalikan top/height ke 0/100%,
        karena di bawah 1024px sidebar adalah drawer full-layar yang SENGAJA
        menutupi kapsul -- tidak ada yang perlu dihindari di sana.
    --}}
    @if (filament()->hasTopbar())
        <style>
            .fi-panel-welcome .fi-sidebar,
            .fi-panel-user .fi-sidebar,
            .fi-panel-admin .fi-sidebar {
                top: var(--fi-capsule-stack);
                bottom: 0;

                /* Dua UJUNG ATAS tumpul. Sidebar menyambung ke kapsul
                   tepat di atasnya, jadi bentuknya harus terbaca sebagai
                   satu kesimpulan dari header, bukan dua kotak yang
                   kebetulan bersentuhan. Bawahnya tetap siku: sidebar
                   menyambung ke tepi layar dan tepi bawah layar, jadi
                   membulatkan bawahnya akan membuatnya terlihat melayang. */
                border-top-left-radius: var(--fi-capsule-radius);
                border-top-right-radius: var(--fi-capsule-radius);
            }

            @media (max-width: 1023px) {
                :root:not(.native-desktop) .fi-panel-welcome .fi-sidebar,
                :root:not(.native-desktop) .fi-panel-user .fi-sidebar,
                :root:not(.native-desktop) .fi-panel-admin .fi-sidebar {
                    top: 0;
                    bottom: 0;
                    border-top-left-radius: 0;
                    border-top-right-radius: 0;
                }
            }
        </style>
    @endif

{{-- format-ignore-start --}}
<aside
    x-data="{}"
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
            class="fi-sidebar-header flex h-12 items-center bg-white px-6 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 lg:shadow-sm"
        >
            {{-- Header ini SENGAJA hanya berisi tombol kontrol (tutup / lebarkan /
                 ciutkan). Logo dipindahkan ke baris switcher di bawah.

                 Alasannya: logo dan switcher tema + bahasa adalah dua kelompok
                 kontrol yang sama untuk satu orang -- identitas di kiri,
                 preferensi di kanan. Ditumpuk dalam satu baris, keduanya
                 terbaca sebagai satu blok. Logo sendirian di baris paling atas
                 dengan switcher jauh di bawahnya justru terlihat mengambang
                 dan terpisah.

                 `ms-auto` di tombol tutup dikembalikan ke miliknya sendiri,
                 karena sekarang tidak ada elemen lain di header ini. --}}
            @if (filament()->isSidebarCollapsibleOnDesktop())
                <x-filament::icon-button
                    color="gray"
                    :icon="$isRtl ? '/images/icons/chevron_left.svg' : '/images/icons/chevron_right.svg'"
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
                    :icon="$isRtl ? '/images/icons/chevron_right.svg' : '/images/icons/chevron_left.svg'"
                    {{-- @deprecated Use `panels::sidebar.collapse-button.rtl` instead of `panels::sidebar.collapse-button` for RTL. --}}
                    :icon-alias="$isRtl ? ['panels::sidebar.collapse-button.rtl', 'panels::sidebar.collapse-button'] : 'panels::sidebar.collapse-button'"
                    icon-size="lg"
                    :label="__('filament-panels::layout.actions.sidebar.collapse.label')"
                    x-cloak
                    x-data="{}"
                    x-on:click="$store.sidebar.close()"
                    x-show="$store.sidebar.isOpen"
                    class="ms-auto hidden lg:flex"
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

        {{-- BARIS LOGO + SWITCHER

             Dua perubahan terhadap view Filament, dan hanya dua:

             1. Logo pindah dari <header> ke sini, sebaris dengan switcher tema
                dan bahasa. Kepala header sekarang hanya tombol tutup di kanan.

             2. Hook SIDEBAR_NAV_START -- yang tadinya dipanggil di dalam <nav>
                -- dipanggil di sini, bukan di dalam wrapper-nya sendiri.
                Hook tetap deciding KAPAN switcher tampil
                (AppPlatform::switchersBelongInSidebar()); file ini yang
                menentukan DI MANA posisinya. Kalau wrapper-nya ikut di
                provider, keputusan tata letak akan tersebar di dua file yang
                tidak saling tahu.

             Baris ini DI LUAR <nav> dengan sengaja: <nav> yang bisa di-scroll,
             dan logo tidak boleh ikut hilang saat daftar menu digulir.

             Wrapper switcher (`ms-auto`) dirender tanpa syarat, jadi di tablet,
             website desktop, dan APLIKASI DESKTOP -- tempat switcher tetap di
             topbar dan hook ini mengembalikan string kosong -- logo tetap
             terdorong ke kiri dan tidak ikut melebar.

             Hook di sini HANYA untuk HP, mobile web, dan aplikasi mobile --
             permukaan tempat switcher milik sidebar.

             Hook dipanggil di sini, bukan me-render view switcher langsung,
             supaya ketiga panel tetap memakai kode yang sama -- file ini
             identik di Welcome/User/Admin.

             CATATAN: file ini adalah salinan view Filament. Kalau
             filament/filament di-upgrade, bandingkan dulu dengan
             vendor/filament/filament/resources/views/components/sidebar/
             index.blade.php -- jangan ditimpa buta, blok di atas adalah
             satu-satunya beda yang disengaja.
        --}}
        <div class="fi-sidebar-switchers flex items-center gap-2 px-6 pb-2">
            <div
                @if (filament()->isSidebarCollapsibleOnDesktop())
                    x-show="$store.sidebar.isOpen"
                    x-transition:enter="lg:transition lg:delay-100"
                    x-transition:enter-start="opacity-0"
                    x-transition:enter-end="opacity-100"
                @endif
            >
                @if ($homeUrl = filament()->getHomeUrl())
                    <a {{ \Filament\Support\generate_href_html($homeUrl) }}>
                        <x-filament-panels::logo />
                    </a>
                @else
                    <x-filament-panels::logo />
                @endif
            </div>

            <div class="ms-auto flex items-center gap-2">
                {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIDEBAR_NAV_START) }}
            </div>
        </div>
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
</aside>
{{-- format-ignore-end --}}

@endif
