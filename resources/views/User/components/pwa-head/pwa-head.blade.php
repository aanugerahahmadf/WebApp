@if (config('platform.pwa.enabled', true))
    <link rel="manifest" href="{{ config('platform.pwa.manifest_path', '/manifest.webmanifest') }}">
    <meta name="theme-color" content="{{ config('platform.pwa.theme_color', '#fbbf24') }}">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="{{ __('Dekorasi Bunga Pernikahan') }}">
    <link rel="apple-touch-icon" href="/images/logo.png">
@endif
