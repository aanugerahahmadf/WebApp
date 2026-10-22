<div
    x-data="{ theme: null }"
    x-init="
        $watch('theme', () => {
            $dispatch('theme-changed', theme)
        })

        theme = localStorage.getItem('theme') || @js(filament()->getDefaultThemeMode()->value)
    "
    class="fi-theme-switcher grid grid-flow-col gap-x-1"
>
    <x-filament-panels::theme-switcher.button
        icon="/images/icons/sun.svg"
        theme="light"
    />

    <x-filament-panels::theme-switcher.button
        icon="/images/icons/moon.svg"
        theme="dark"
    />

    <x-filament-panels::theme-switcher.button
        icon="/images/icons/computer_desktop.svg"
        theme="system"
    />
</div>
