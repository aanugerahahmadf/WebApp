<x-filament-panels::page>
    <div class="space-y-6">

        {{-- ── BAHASA ────────────────────────────────────────────── --}}
        <div id="bahasa">
            @livewire('mobile-settings-component')
        </div>

        {{-- ── TAMPILAN & DATA BROWSER ─────────────────────────── --}}
        <x-filament::section aside icon="heroicon-o-swatch" :heading="__('Data Browser')" :description="__('Hapus data lokal aplikasi pada browser ini.')">
            <div class="flex justify-end">
                <x-filament::button x-on:click="localStorage.removeItem('notification_prefs'); localStorage.removeItem('theme'); $dispatch('notify', { status: 'success', title: '{{ __('Data browser dibersihkan') }}' })" color="danger" size="sm">{{ __('Bersihkan Data Browser') }}</x-filament::button>
            </div>
        </x-filament::section>

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
                    color="primary"
                    size="sm"
                >
                    {{ __('Buka') }}
                </x-filament::button>
            </div>
        </x-filament::section>

        {{-- ── KATA SANDI & KEAMANAN ─────────────────────────────── --}}
        @livewire('security-settings-component')

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
