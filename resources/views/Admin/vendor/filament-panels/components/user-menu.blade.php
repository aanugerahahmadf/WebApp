@php
    $user = filament()->auth()->user();
    $items = filament()->getUserMenuItems();

    // Placement dropdown mengikuti pemanggil: sidebar dasar panel admin
    // memakai `top-start` (dropdown membuka ke ATAS, karena pemicunya di
    // ujung bawah layar); pemanggil lain tetap bawaan `bottom-end`.
    $placement = $attributes->get('placement') ?? 'bottom-end';
    $attributes = $attributes->except(['placement']);

    $profileItem = $items['profile'] ?? $items['account'] ?? null;
    $profileItemUrl = $profileItem?->getUrl();
    $profilePage = filament()->getProfilePage();
    $hasProfileItem = filament()->hasProfile() || filled($profileItemUrl);

    $logoutItem = $items['logout'] ?? null;

    $items = \Illuminate\Support\Arr::except($items, ['account', 'logout', 'profile']);
@endphp

{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_BEFORE) }}

<x-filament::dropdown
    :placement="$placement"
    x-on:mouseenter="clearTimeout($el._hoverTimer); open($event)"
    x-on:mouseleave="$el._hoverTimer = setTimeout(() => close($event), 220)"
    :attributes="
        \Filament\Support\prepare_inherited_attributes($attributes)
            ->class(['fi-user-menu'])
    "
>
    <x-slot name="trigger">
        {{--
            BLOK PROFIL GAYA LARAVEL STARTER KIT (2026-10-25).

            Dulu pemicunya HANYA avatar (tombol bulat kecil). Permintaannya
            blok profil di dasar sidebar menampilkan NAMA, username, dan
            email -- seperti sidebar Laravel Starter Kit: avatar di kiri,
            tiga baris teks di kanannya, seluruh baris jadi satu tombol yang
            membuka dropdown (Edit Profil / Sign Out).

            `fi-avatar-menu-trigger` dan `fi-user-menu` dipertahankan sebagai
            penanda yang dibaca test penjaga (WelcomeStorefrontGuestAccessTest,
            TopbarAccountControlTest).
        --}}
        <button
            aria-label="{{ __('filament-panels::layout.actions.open_user_menu.label') }}"
            type="button"
            style="background: transparent !important; background-color: transparent !important; background-image: none !important; backdrop-filter: none !important; -webkit-backdrop-filter: none !important;"
            class="fi-avatar-menu-trigger flex w-full shrink-0 items-center gap-3 bg-transparent text-start hover:bg-transparent focus:bg-transparent active:bg-transparent"
        >
            <x-filament-panels::avatar.user :user="$user" />

            <span class="flex min-w-0 flex-col">
                <span class="truncate text-sm font-semibold leading-tight text-gray-950 dark:text-white">
                    {{ filament()->getUserName($user) }}
                </span>

                @if (filled($username = $user?->getAttribute('username')))
                    <span class="truncate text-xs leading-tight text-gray-500 dark:text-gray-400">
                        {{ $username }}
                    </span>
                @endif

                <span class="truncate text-[11px] leading-tight text-gray-400 dark:text-gray-500">
                    {{ $user?->getAttribute('email') }}
                </span>
            </span>
        </button>
    </x-slot>

    @if ($profileItem?->isVisible() ?? true)
        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_BEFORE) }}

        @if ($hasProfileItem)
            <x-filament::dropdown.list>
                <x-filament::dropdown.list.item
                    :color="$profileItem?->getColor()"
                    :icon="$profileItem?->getIcon() ?? \Filament\Support\Facades\FilamentIcon::resolve('panels::user-menu.profile-item') ?? '/images/icons/user_circle.svg'"
                    :href="$profileItemUrl ?? filament()->getProfileUrl()"
                    :target="($profileItem?->shouldOpenUrlInNewTab() ?? false) ? '_blank' : null"
                    tag="a"
                >
                    {{ $profileItem?->getLabel() ?? ($profilePage ? $profilePage::getLabel() : null) ?? filament()->getUserName($user) }}
                </x-filament::dropdown.list.item>
            </x-filament::dropdown.list>
        @else
            <x-filament::dropdown.header
                :color="$profileItem?->getColor()"
                :icon="$profileItem?->getIcon() ?? \Filament\Support\Facades\FilamentIcon::resolve('panels::user-menu.profile-item') ?? '/images/icons/user_circle.svg'"
            >
                {{ $profileItem?->getLabel() ?? filament()->getUserName($user) }}
            </x-filament::dropdown.header>
        @endif

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_PROFILE_AFTER) }}
    @endif

    {{--
        TIDAK ADA switcher tema di user menu.

        Bawaan Filament menaruh `<x-filament-panels::theme-switcher />` di sini
        (tiga ikon sun/moon/monitor). Panel admin sekarang punya switcher tema
        sendiri di TOPBAR -- dropdown satu tombol dari partial
        `Shared.components.theme-switcher.theme-switcher`, dirender lewat hook
        `panels::global-search.after` di AdminPanelProvider.

        Alasannya: aturan switcher di aplikasi ini berlaku untuk KETIGA panel
        dengan satu predikat yang sama:

          - permukaan non-HP (tablet, website desktop, APLIKASI DESKTOP)
            -> switcher di TOPBAR
          - HP, mobile web, aplikasi mobile -> switcher di SIDEBAR

        Switcher di user menu tidak mengikuti aturan itu: dia tersembunyi satu
        klik di dalam dropdown avatar, bukan di barisan kontrol topbar yang
        kelihatan -- dan di aplikasi desktop yang jendelannya bisa dikecilkan,
        user menu adalah tempat yang paling mudah terpotong.

        Kalau switcher ini dihidupkan lagi, Tema muncul dua kali di panel yang
        sama. Test penjaga: `tests/Feature/Shared/SwitcherPlacementTest/` dan
        `tests/Feature/Shared/ThemeSwitcherOnceTest/`.
    --}}

    <x-filament::dropdown.list>
        @foreach ($items as $key => $item)
            @php
                $itemPostAction = $item->getPostAction();
            @endphp

            <x-filament::dropdown.list.item
                :action="$itemPostAction"
                :color="$item->getColor()"
                :href="$item->getUrl()"
                :icon="$item->getIcon()"
                :method="filled($itemPostAction) ? 'post' : null"
                :tag="filled($itemPostAction) ? 'form' : 'a'"
                :target="$item->shouldOpenUrlInNewTab() ? '_blank' : null"
            >
                {{ $item->getLabel() }}
            </x-filament::dropdown.list.item>
        @endforeach

        <x-filament::dropdown.list.item
            :action="$logoutItem?->getUrl() ?? filament()->getLogoutUrl()"
            :color="$logoutItem?->getColor()"
            :icon="$logoutItem?->getIcon() ?? \Filament\Support\Facades\FilamentIcon::resolve('panels::user-menu.logout-button') ?? '/images/icons/sign_out.svg'"
            method="post"
            tag="form"
        >
            {{ $logoutItem?->getLabel() ?? __('filament-panels::layout.actions.logout.label') }}
        </x-filament::dropdown.list.item>
    </x-filament::dropdown.list>
</x-filament::dropdown>

{{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::USER_MENU_AFTER) }}
