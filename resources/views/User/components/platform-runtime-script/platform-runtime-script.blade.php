@php
    use App\Support\PlatformContext\PlatformContext;
    $platform = PlatformContext::current(request());
    $cbirMode = $platform->cbirCameraMode();
    $cbirMode = in_array($cbirMode, ['native', 'mobile_browser_capture', 'webrtc']) ? $cbirMode : 'webrtc';
@endphp
<script>
(function () {
    try {
        // ── 1. Expose server-detected platform to JavaScript ──────────────
        // window.AppPlatform is the single source of truth for all client-side
        // platform checks. It mirrors the server-resolved RuntimePlatform enum.
        window.AppPlatform = {
            slug:            @json($platform->value),
            label:           @json($platform->label()),
            isWebsite:       @json($platform->isWebsite()),
            isDesktopApp:    @json($platform->isDesktopApp()),
            isMobileApp:     @json($platform->isMobileApp()),
            isMobileShell:   @json($platform->isMobileShell()),
            cbirCameraMode:  @json($cbirMode),
        };

        // ── 2. Convenience shorthands ─────────────────────────────────────
        window.AppPlatform.isNativeMobile  = window.AppPlatform.isMobileApp;
        window.AppPlatform.isNativeDesktop = window.AppPlatform.isDesktopApp;
        window.AppPlatform.isAndroid       = window.AppPlatform.slug === 'mobile_app_android';
        window.AppPlatform.isIos           = window.AppPlatform.slug === 'mobile_app_ios';
        window.AppPlatform.isWindows       = window.AppPlatform.slug === 'desktop_app_windows'
                                          || window.AppPlatform.slug === 'website_windows';
        window.AppPlatform.isMac           = window.AppPlatform.slug === 'desktop_app_macos'
                                          || window.AppPlatform.slug === 'website_macos';

        // ── 3. PWA standalone cookie ──────────────────────────────────────
        // Set once; never overwrite if already present.
        var standalone = window.matchMedia('(display-mode: standalone)').matches
            || window.navigator.standalone === true;

        if (standalone && document.cookie.indexOf('app_display_mode=standalone') === -1) {
            document.cookie = 'app_display_mode=standalone;path=/;max-age=31536000;SameSite=Lax';
        }

        // ── 4. Body data-attribute for CSS targeting ──────────────────────
        // Allows CSS like: body[data-platform="mobile_app_android"] { ... }
        document.documentElement.setAttribute('data-platform', window.AppPlatform.slug);
        if (window.AppPlatform.isMobileApp)  document.documentElement.classList.add('native-mobile');
        if (window.AppPlatform.isDesktopApp) document.documentElement.classList.add('native-desktop');

    } catch (e) {
        console.error('[AppPlatform] Runtime script error:', e);
    }
})();
</script>
