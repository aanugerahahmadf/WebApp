import preset from '../../../../vendor/filament/filament/tailwind.config.preset'

export default {
    presets: [preset],
    content: [
        './app/Filament/**/*.php',
        './resources/views/vendor/filament/**/*.blade.php',
        './vendor/filament/**/*.blade.php',
    ],
}