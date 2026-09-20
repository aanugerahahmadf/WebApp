@props([
    'heading' => null,
    'subheading' => null,
])

<div {{ $attributes->class(['fi-simple-page']) }}>
    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_PAGE_START, scopes: $this->getRenderHookScopes()) }}

    @php
        $resolvedHeading = $heading ??= $this->getHeading();
        $resolvedSubheading = $subheading ??= $this->getSubHeading();
        $isAuthenticationPage = str_contains($this::class, '\\Auth\\');
    @endphp

    @if ($isAuthenticationPage)
        {{--
            Keep the brand, page heading, supporting text, and authentication
            form together. This uses Filament's native Section component so
            every User and Admin authentication screen has one clear surface.
        --}}
        <x-filament::section>
            <div class="grid auto-cols-fr gap-y-6">
                <x-filament-panels::header.simple
                    :heading="$resolvedHeading"
                    :logo="$this->hasLogo()"
                    :subheading="$resolvedSubheading"
                />

                {{ $slot }}
            </div>
        </x-filament::section>
    @else
        <section class="grid auto-cols-fr gap-y-6">
            <x-filament-panels::header.simple
                :heading="$resolvedHeading"
                :logo="$this->hasLogo()"
                :subheading="$resolvedSubheading"
            />

            {{ $slot }}
        </section>
    @endif

    @if (! $this instanceof \Filament\Tables\Contracts\HasTable)
        <x-filament-actions::modals />
    @endif

    {{ \Filament\Support\Facades\FilamentView::renderHook(\Filament\View\PanelsRenderHook::SIMPLE_PAGE_END, scopes: $this->getRenderHookScopes()) }}
</div>
