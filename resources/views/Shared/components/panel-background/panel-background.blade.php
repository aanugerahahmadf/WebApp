@php
    $background = \App\Models\FmAppSetting\FmAppSetting::background();
    $mediaPath = $background['type'] === 'video' ? $background['video'] : $background['image'];
    $mediaUrl = null;
    // Storefront panels memakai permukaan polos mengikuti mode tema. Jangan
    // render media background untuk Welcome/User; gambar background hanya
    // relevan untuk panel lain yang memang memakainya.
    $isStorefrontPanel = in_array(filament()->getCurrentPanel()?->getId(), ['welcome', 'user'], true);
    // Background image diminta tetap tajam. Nilai blur lama dari pengaturan
    // aplikasi tidak lagi diterapkan ke gambar/video panel.
    $blur = 0;

    if (filled($mediaPath)) {
        $mediaUrl = \Illuminate\Support\Str::startsWith((string) $mediaPath, ['http://', 'https://', '/'])
            ? $mediaPath
            : \Illuminate\Support\Facades\Storage::disk('public')->url($mediaPath);
    }
@endphp

@if (! $isStorefrontPanel && $background['active'] && filled($mediaUrl))
    <div
        class="panel-background"
        data-panel-background
        data-parallax-strength="{{ (int) $background['parallax_strength'] }}"
        data-parallax-speed="{{ (float) $background['parallax_speed'] }}"
        style="--panel-overlay: {{ $background['overlay'] }}; --panel-overlay-opacity: {{ ((int) $background['overlay_opacity']) / 100 }}; --panel-blur: {{ $blur }}px;"
        aria-hidden="true"
    >
        <div class="panel-background-media" data-panel-background-media>
            @if ($background['type'] === 'video')
                <video src="{{ $mediaUrl }}" autoplay muted loop playsinline preload="metadata"></video>
            @else
                <img src="{{ $mediaUrl }}" alt="" loading="eager" />
            @endif
        </div>
    </div>

    <style data-panel-background-styles>
        .fi-body.fi-panel-user,
        .fi-body.fi-panel-welcome,
        .fi-body.fi-panel-admin { position: relative; }
        /* HANYA layout utama yang diangkat di atas lapisan latar.
           JANGAN memakai `> :not([data-panel-background])` di sini: aturan
           itu juga mengenai panel dropdown yang di-teleport ke <body>
           (.lang-dd, .fi-dropdown-panel) dan overlay modal, memaksa
           `position: relative` menimpa utility `fixed`. Akibatnya language
           switcher, switcher tema, dan profil jatuh ke dasar halaman
           (tidak terlihat) dan modal ikut salah posisi. */
        .fi-body.fi-panel-user > .fi-layout,
        .fi-body.fi-panel-welcome > .fi-layout,
        .fi-body.fi-panel-admin > .fi-layout { position: relative; z-index: 1; }
        [data-panel-background] {
            position: fixed; inset: 0; z-index: 0; overflow: hidden;
            pointer-events: none; isolation: isolate;
        }
        [data-panel-background]::after {
            content: ''; position: absolute; inset: 0; z-index: 2;
            background: var(--panel-overlay); opacity: var(--panel-overlay-opacity);
        }
        [data-panel-background-media] {
            position: absolute; inset: -90px; will-change: transform;
        }
        [data-panel-background-media] img,
        [data-panel-background-media] video {
            display: block; width: 100%; height: 100%; object-fit: cover;
            filter: none;
            -webkit-filter: none;
        }
        @media (prefers-reduced-motion: reduce) {
            [data-panel-background-media] { transform: none !important; }
        }
    </style>

    <script data-panel-background-script>
        (() => {
            const root = document.querySelector('[data-panel-background]');
            const media = root?.querySelector('[data-panel-background-media]');
            if (!root || !media || root.dataset.ready) return;
            root.dataset.ready = '1';
            const strength = Number(root.dataset.parallaxStrength || 18);
            const speed = Number(root.dataset.parallaxSpeed || 0.12);
            let targetX = 0, targetY = 0, x = 0, y = 0, frame = null;
            const render = () => {
                x += (targetX - x) * speed;
                y += (targetY - y) * speed;
                media.style.transform = `translate3d(${x}px, ${y}px, 0)`;
                frame = (Math.abs(targetX - x) + Math.abs(targetY - y) > 0.05)
                    ? requestAnimationFrame(render) : null;
            };
            window.addEventListener('pointermove', (event) => {
                targetX = ((event.clientX / window.innerWidth) - 0.5) * strength * 2;
                targetY = ((event.clientY / window.innerHeight) - 0.5) * strength * 2;
                if (!frame) frame = requestAnimationFrame(render);
            }, { passive: true });
            window.addEventListener('pointerleave', () => {
                targetX = 0; targetY = 0;
                if (!frame) frame = requestAnimationFrame(render);
            });
        })();
    </script>
@endif
