@php
    use Filament\Support\Enums\MaxWidth;

    $livewire ??= null;
    $isAuthenticationPage = $livewire && str_contains($livewire::class, '\\Auth\\');
@endphp

<x-filament-panels::layout.base :livewire="$livewire">
    @props([
        'after' => null,
        'heading' => null,
        'subheading' => null,
    ])

    {{-- Penanda halaman auth: dipakai AppFont::styles() untuk memaksa
         Instrument Sans + semibold (tanpa bold) hanya di halaman auth. --}}
    <div
        @if ($isAuthenticationPage) data-auth-page @endif
        class="fi-simple-layout flex min-h-screen flex-col items-center"
    >
        @if ($isAuthenticationPage)
            <style data-auth-transparent-surfaces>
                [data-auth-page] .fi-simple-main,
                [data-auth-page] .fi-simple-main-ctn,
                [data-auth-page] section.fi-section,
                [data-auth-page] .fi-section-content-ctn,
                [data-auth-page] .fi-fo-wizard,
                [data-auth-page] .fi-fo-wizard-header {
                    background: transparent !important;
                    background-color: transparent !important;
                    background-image: none !important;
                    box-shadow: none !important;
                    --tw-ring-shadow: 0 0 #0000 !important;
                }

                [data-auth-page] .fi-simple-main {
                    border: 0 !important;
                    border-radius: 0 !important;
                }
            </style>
        @endif
        @unless ($isAuthenticationPage)
            <div
                class="absolute end-0 top-0 flex h-16 items-center gap-x-4 pe-4 md:pe-6 lg:pe-8 z-20"
            >
                @include('User.filament-language-switcher.language-switcher.language-switcher')

                @if (($hasTopbar ?? true) && filament()->auth()->check())
                    @if (filament()->hasDatabaseNotifications())
                        @livewire(Filament\Livewire\DatabaseNotifications::class, [
                            'lazy' => filament()->hasLazyLoadedDatabaseNotifications()
                        ])
                    @endif

                    <x-filament-panels::user-menu />
                @endif
            </div>
        @endunless

        <div
            class="fi-simple-main-ctn flex w-full flex-grow items-center justify-center"
        >
            <main
                @class([
                    'fi-simple-main my-16 w-full bg-white px-6 py-12 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:rounded-xl sm:px-12',
                    match ($maxWidth ??= (filament()->getSimplePageMaxContentWidth() ?? MaxWidth::Large)) {
                        MaxWidth::ExtraSmall, 'xs' => 'max-w-xs',
                        MaxWidth::Small, 'sm' => 'max-w-sm',
                        MaxWidth::Medium, 'md' => 'max-w-md',
                        MaxWidth::Large, 'lg' => 'max-w-lg',
                        MaxWidth::ExtraLarge, 'xl' => 'max-w-xl',
                        MaxWidth::TwoExtraLarge, '2xl' => 'max-w-2xl',
                        MaxWidth::ThreeExtraLarge, '3xl' => 'max-w-3xl',
                        MaxWidth::FourExtraLarge, '4xl' => 'max-w-4xl',
                        MaxWidth::FiveExtraLarge, '5xl' => 'max-w-5xl',
                        MaxWidth::SixExtraLarge, '6xl' => 'max-w-6xl',
                        MaxWidth::SevenExtraLarge, '7xl' => 'max-w-7xl',
                        MaxWidth::Full, 'full' => 'max-w-full',
                        MaxWidth::MinContent, 'min' => 'max-w-min',
                        MaxWidth::MaxContent, 'max' => 'max-w-max',
                        MaxWidth::FitContent, 'fit' => 'max-w-fit',
                        MaxWidth::Prose, 'prose' => 'max-w-prose',
                        MaxWidth::ScreenSmall, 'screen-sm' => 'max-w-screen-sm',
                        MaxWidth::ScreenMedium, 'screen-md' => 'max-w-screen-md',
                        MaxWidth::ScreenLarge, 'screen-lg' => 'max-w-screen-lg',
                        MaxWidth::ScreenExtraLarge, 'screen-xl' => 'max-w-screen-xl',
                        MaxWidth::ScreenTwoExtraLarge, 'screen-2xl' => 'max-w-screen-2xl',
                        default => $maxWidth,
                    },
                ])
            >
                {{ $slot }}
            </main>
        </div>

        {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::FOOTER, scopes: $livewire?->getRenderHookScopes()) }}
    </div>
</x-filament-panels::layout.base>
