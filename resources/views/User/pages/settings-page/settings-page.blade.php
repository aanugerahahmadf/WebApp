<x-filament-panels::page>
    @php
        $theme = request()->cookie('theme') ?? filament()->getDefaultThemeMode()->value;
    @endphp
    <div class="space-y-6">

        {{-- ── BAHASA ────────────────────────────────────────────── --}}
        <div id="bahasa">
            @livewire('mobile-settings-component')
        </div>

        {{-- ── PENGATURAN NOTIFIKASI ───────────────────────────── --}}
        @livewire('notification-settings-component')

        {{-- ── LAPORAN ───────────────────────────────────────────── --}}
        <x-filament::section
            aside
            icon="heroicon-o-flag"
            :heading="__('Lapor')"
            :description="__('Laporkan masalah atau sampaikan keluhan melalui chat.')"
        >
            <div class="flex items-center justify-end">
                <x-filament::button
                    tag="a"
                    :href="route('filament.user.pages.messages.{id?}')"
                    icon="heroicon-m-chevron-right"
                    icon-position="after"
                    color="gray"
                    size="sm"
                >
                    {{ __('Buka') }}
                </x-filament::button>
            </div>
        </x-filament::section>

        {{-- ── KATA SANDI & KEAMANAN ─────────────────────────────── --}}
        @livewire('edit-password-component')

        {{-- ── KUNCI APLIKASI ────────────────────────────────────── --}}
        @livewire('app-lock-component')

        {{-- ── SESI & HAPUS AKUN ─────────────────────────────────── --}}
        @foreach ($this->getRegisteredCustomProfileComponents() as $component)
            @unless(is_null($component))
                @livewire($component, [], key($component))
            @endunless
        @endforeach

    </div>
</x-filament-panels::page>