@php
    $user = filament()->auth()->user();
    $items = filament()->getUserMenuItems();

    // Placement dropdown mengikuti pemanggil: blok profil di dasar sidebar
    // memakai `top-start` (dropdown membuka ke ATAS); pemanggil lain seperti
    // topbar tetap memakai bawaan `bottom-end`.
    $placement = $attributes->get('placement') ?? 'bottom-end';

    // Hanya sidebar yang meminta blok profil lengkap (avatar + nama +
    // username + email). Topbar tetap memakai trigger avatar ringkas.
    $showProfileDetails = (bool) ($attributes->get('profile-block') ?? false);

    $attributes = $attributes->except(['placement', 'profile-block']);

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
        @if ($showProfileDetails)
            {{-- Blok profil di dasar sidebar: avatar + nama + username + email.
                 `fi-avatar-menu-trigger` dipertahankan sebagai penanda test. --}}
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
        @else
            <button
                aria-label="{{ __('filament-panels::layout.actions.open_user_menu.label') }}"
                type="button"
                class="fi-avatar-menu-trigger shrink-0"
            >
                <x-filament-panels::avatar.user :user="$user" />
            </button>
        @endif
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
        (tiga ikon sun/moon/monitor). Di panel ini switcher tema sudah ada di
        TOPBAR -- dropdown satu tombol, dirender lewat hook
        `panels::global-search.after` di UserPanelProvider, dengan partial yang
        sama persis dengan panel Welcome
        (`Shared.components.theme-switcher.theme-switcher`).

        Dua switcher untuk satu pengaturan = user tidak tahu mana yang
        dipakai, dan topbar jadi lebih padat. Yang dipegang: TOPBAR (tablet,
        website desktop, APLIKASI DESKTOP) atau SIDEBAR (HP, mobile web,
        aplikasi mobile) -- ditentukan oleh predikat yang sama,
        `AppPlatform::switchersBelong*()`.

        Kalau switcher di user menu dihidupkan lagi, tema akan muncul dua
        kali. Lihat test penjaga: `tests/Feature/Shared/ThemeSwitcherOnceTest/`.
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