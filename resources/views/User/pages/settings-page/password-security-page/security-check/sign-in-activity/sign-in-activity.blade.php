{{--
    Sesi perangkat yang aktif.

    Daftar sesi dirender oleh BrowserSessionsComponent yang hidup di folder ini
    juga (SecurityCheck/SignInActivity/BrowserSessionsComponent).
--}}
<x-filament-panels::page>
    <div class="mx-auto max-w-4xl">
        <livewire:browser_sessions_form />
    </div>
</x-filament-panels::page>