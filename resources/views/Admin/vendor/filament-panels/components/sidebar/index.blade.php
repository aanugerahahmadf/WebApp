@props([
    'navigation',
])

@php
    $openSidebarClasses = 'fi-sidebar-open w-[var(--sidebar-width)] translate-x-0 shadow-xl ring-1 ring-gray-950/5 dark:ring-white/10 rtl:-translate-x-0';
    $isRtl = __('filament-panels::layout.direction') === 'rtl';
@endphp

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
            'fi-sidebar fixed inset-y-0 start-0 z-30 flex flex-col h-screen content-start bg-white transition-all dark:bg-gray-900 lg:z-0 lg:bg-transparent lg:shadow-none lg:ring-0 lg:transition-none dark:lg:bg-transparent',
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
                    :icon="$isRtl ? 'heroicon-o-chevron-left' : 'heroicon-o-chevron-right'"
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
                    :icon="$isRtl ? 'heroicon-o-chevron-right' : 'heroicon-o-chevron-left'"
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
                icon="heroicon-o-x-mark"
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

             Wrapper switcher (`ms-auto`) dirender tanpa syarat, jadi di tablet
             dan website desktop -- tempat switcher tetap di topbar dan hook ini
             mengembalikan string kosong -- logo tetap terdorong ke kiri dan
             tidak ikut melebar.

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
