{{--
    Halaman error "glass" + animasi -- SATU-SATUNYA sumber tampilan.

    Dipakai oleh tiga kelompok view (Welcome, User, Admin). Semua view itu
    hanya meneruskan kode status ke sini:

        @include('Shared.errors.glass.glass', ['status' => 404])

    Kenapa satu file, bukan satu file per status:
      - 8 status x 3 panel = 24 salinan. Salinan pasti akan ada yang
        tertinggal, dan yang tertinggal biasanya tidak pernah dibuka.
      - Isi halaman (judul, warna, tombol) sudah punya satu sumber
        lain: `App\Support\ErrorPage\ErrorPage`.

    VARIABEL YANG DITERIMA:
      - $status          Kode status HTTP (default 404).
      - $messageOverride Pesan yang dipakai sebagai judul, kalau view di atas
                         menyediakannya (dipakai oleh namespace `errors::`).

    ISI HALAMANNYA SENGJAJA SINGKAT: kode, judul, tombol. Tidak ada ikon
    status, tidak ada kalimat penjelasan, dan TIDAK ADA pesan exception.

    Pesan exception tidak pernah ditampilkan ke pengguna: yang muncul di
    produksi adalah kalimat seperti "The route xyz could not be found" --
    membocorkan struktur routing ke pengunjung. Detail teknisnya ada di log.

    CATATAN SOAL DATABASE:
      Halaman error boleh muncul justru saat database tidak bisa dijangkau,
      jadi halaman ini TIDAK boleh query apa pun. Karena itu nama brand pun
      tidak ditampilkan di sini -- dan `AppFont` (yang meng-cache Setting
      dari database) tidak pernah dipanggil.

    CATATAN SOAL BAHASA:
      Teks halaman ini mengikuti bahasa yang sedang dipakai, dan bahasa itu
      ditentukan oleh `App\Support\AppLocale\AppLocale` -- bukan oleh
      middleware `SetLocale`.

      Alasannya: 404 untuk URL yang tidak dikenal dilempar oleh ROUTER,
      SEBELUM middleware route (termasuk grup `web`) sempat jalan. Jadi
      `SetLocale` tidak pernah dipanggil, dan tanpa langkah tambahan di
      exception handler, halaman error selalu memakai default aplikasi --
      bukan bahasa yang diminta browser.
--}}
@php
    // Kode status.
    //
    // Kalau view pemanggil tidak_MEMBERI kode (namespace `errors::` milik
    // Laravel hanya punya 401/402/403/404/419/429/500/503, jadi status lain
    // -- misalnya 405 -- jatuh ke view tanpa section 'code'), kodenya diambil
    // dari exception-nya. Tanpa itu, halaman 405 akan menampilkan angka 404.
    $gxStatus = (int) ($status ?? 0);

    if ($gxStatus <= 0) {
        $gxStatus = (int) (($exception ?? null)?->getStatusCode() ?? 0);
    }

    if ($gxStatus <= 0) {
        $gxStatus = 404;
    }

    $errorPage = \App\Support\ErrorPage\ErrorPage::for($gxStatus);

    $errorTitle = trim((string) ($messageOverride ?? ''));

    if ($errorTitle !== '') {
        $errorPage['title'] = $errorTitle;
    }
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="color-scheme" content="light dark">

    {{-- Font aplikasi: @font-face Instrument Sans tanpa query database
         (link CSS statis, bukan AppFont::styles() yang membaca Setting). --}}
    <link rel="stylesheet" href="/css/app-font.css">

    <title>{{ $errorPage['title'] }} &middot; {{ $errorPage['code'] }}</title>

    {{--
        Mode gelap harus dipasang SEBELUM halaman digambar, kalau tidak
        akan ada kedipan putih (flash) di halaman gelap yang dibuka dari
        panel yang sedang gelap.

        Kuncinya sama dengan milik Filament: `localStorage['theme']` berisi
        'light' | 'dark' | 'system'. Jadi tombol tema di panel dan halaman
        error ini konsisten -- ganti tema di panel, lalu buka halaman error,
        warnanya sudah sama.
    --}}
    <script>
        (function () {
            try {
                var stored = window.localStorage.getItem('theme') || 'system';
                var dark = stored === 'dark' || (stored === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);

                document.documentElement.classList.toggle('dark', dark);
            } catch (error) {
                // localStorage bisa diblokir (mode privasi). Diamkan saja:
                // halaman tetap tampil, hanya mode gelap tidak dipaksa.
            }
        })();
    </script>

    <style>
        @property --gx-angle {
            syntax: '<angle>';
            initial-value: 0deg;
            inherits: false;
        }

        :root {
            --gx-accent-1: {{ $errorPage['accent'][0] }};
            --gx-accent-2: {{ $errorPage['accent'][1] }};
            --gx-accent-rgb-1: {{ $errorPage['accentRgb'][0] }};
            --gx-accent-rgb-2: {{ $errorPage['accentRgb'][1] }};

            --gx-brand-1: {{ $errorPage['brand'][0] }};
            --gx-brand-2: {{ $errorPage['brand'][1] }};
            --gx-brand-rgb-1: {{ $errorPage['brandRgb'][0] }};
            --gx-brand-rgb-2: {{ $errorPage['brandRgb'][1] }};
            --gx-brand-on: {{ $errorPage['brandOn'] }};

            --gx-bg-top: #eef1f9;
            --gx-bg-bottom: #f8fafc;

            --gx-fg: rgb(3 7 18 / 0.92);

            /* Token kaca disamakan dengan panel (public/css/panel-glass.css):
               latar 20-62%, tint 50%, ring, blur 20px + saturate 180%. */
            --gx-glass-bg: rgb(255 255 255 / 0.55);
            --gx-glass-tint: rgb(255 255 255 / 0.50);
            --gx-glass-ring: rgb(255 255 255 / 0.65);
            --gx-glass-blur: blur(20px) saturate(180%);

            --gx-shadow:
                0 32px 80px -28px rgb(15 23 42 / 0.45),
                0 2px 8px rgb(15 23 42 / 0.06),
                inset 0 1px 0 rgb(255 255 255 / 0.45);

            --gx-grid-line: rgb(15 23 42 / 0.055);
            --gx-noise-opacity: 0.18;
        }

        html.dark {
            --gx-bg-top: #06080f;
            --gx-bg-bottom: #0b1020;

            --gx-fg: rgb(255 255 255 / 0.95);

            /* Kaca gelap: putih tipis, bukan hitam pekat.
               Kalau latarnya hitam (20% hitam + 50% tint hitam seperti di
               panel), orbs di belakangnya hilang dan kartunya jadi kotak
               gelap polos. Di sini kacanya BENAR-BENAR meneruskan. */
            --gx-glass-bg: rgb(255 255 255 / 0.08);
            --gx-glass-tint: rgb(255 255 255 / 0.02);
            --gx-glass-ring: rgb(255 255 255 / 0.16);

            --gx-shadow:
                0 34px 90px -30px rgb(0 0 0 / 0.85),
                inset 0 1px 0 rgb(255 255 255 / 0.16);

            --gx-grid-line: rgb(255 255 255 / 0.045);
            --gx-noise-opacity: 0.10;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html,
        body {
            height: 100%;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: clamp(16px, 4vw, 40px);
            padding-bottom: max(clamp(16px, 4vw, 40px), env(safe-area-inset-bottom));

            font-family: 'Instrument Sans', ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", "Helvetica Neue", Arial, "Noto Sans", sans-serif;
            color: var(--gx-fg);
            background-color: var(--gx-bg-bottom);
            background-image:
                radial-gradient(120% 80% at 50% -10%, rgb(var(--gx-brand-rgb-1) / 0.22) 0%, transparent 62%),
                radial-gradient(90% 70% at 12% 108%, rgb(var(--gx-accent-rgb-1) / 0.20) 0%, transparent 60%),
                linear-gradient(165deg, var(--gx-bg-top) 0%, var(--gx-bg-bottom) 52%, var(--gx-bg-top) 100%);
            background-attachment: fixed;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            overflow-x: hidden;
        }

        /* --- Lapisan latar ---------------------------------------------- */

        .gx-orb {
            position: fixed;
            border-radius: 9999px;
            pointer-events: none;
            will-change: transform;
        }

        .gx-orb--1 {
            top: -14vh;
            left: -10vw;
            width: 52vw;
            height: 52vw;
            min-width: 280px;
            min-height: 280px;
            background: radial-gradient(circle at 35% 35%, rgb(var(--gx-accent-rgb-1) / 0.62), rgb(var(--gx-accent-rgb-2) / 0.12) 62%, transparent 72%);
            filter: blur(70px);
            opacity: 0.9;
            animation: gx-float-a 19s ease-in-out infinite;
        }

        .gx-orb--2 {
            right: -12vw;
            bottom: -16vh;
            width: 56vw;
            height: 56vw;
            min-width: 300px;
            min-height: 300px;
            background: radial-gradient(circle at 60% 40%, rgb(var(--gx-brand-rgb-2) / 0.55), rgb(var(--gx-brand-rgb-1) / 0.10) 60%, transparent 72%);
            filter: blur(80px);
            opacity: 0.85;
            animation: gx-float-b 23s ease-in-out infinite;
        }

        .gx-orb--3 {
            top: 26vh;
            right: 14vw;
            width: 30vw;
            height: 30vw;
            min-width: 200px;
            min-height: 200px;
            background: radial-gradient(circle at 50% 50%, rgb(var(--gx-accent-rgb-2) / 0.48), transparent 70%);
            filter: blur(64px);
            opacity: 0.7;
            animation: gx-float-c 17s ease-in-out infinite;
        }

        .gx-grid {
            position: fixed;
            inset: 0;
            pointer-events: none;
            background-image:
                linear-gradient(to right, var(--gx-grid-line) 1px, transparent 1px),
                linear-gradient(to bottom, var(--gx-grid-line) 1px, transparent 1px);
            background-size: 58px 58px;
            -webkit-mask-image: radial-gradient(72% 62% at 50% 42%, #000 0%, transparent 100%);
            mask-image: radial-gradient(72% 62% at 50% 42%, #000 0%, transparent 100%);
        }

        .gx-noise {
            position: fixed;
            inset: 0;
            pointer-events: none;
            opacity: var(--gx-noise-opacity);
            mix-blend-mode: overlay;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='180' height='180'%3E%3Cfilter id='gx-noise'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23gx-noise)'/%3E%3C/svg%3E");
        }

        /* --- Kartu kaca -------------------------------------------------- */

        .gx-shell {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 34rem;
        }

        .gx-card {
            position: relative;
            overflow: hidden;
            isolation: isolate;
            padding: clamp(26px, 5vw, 40px) clamp(20px, 6vw, 44px);
            border-radius: 28px;
            text-align: center;

            background-color: var(--gx-glass-bg);
            background-image: linear-gradient(var(--gx-glass-tint), var(--gx-glass-tint));
            border: 1px solid var(--gx-glass-ring);

            /* -webkit- wajib: Android WebView dan iOS Safari sudah tidak
               mendukung backdrop-filter tanpa prefiks itu. */
            -webkit-backdrop-filter: var(--gx-glass-blur);
            backdrop-filter: var(--gx-glass-blur);

            box-shadow: var(--gx-shadow);
        }

        /* Cincin gradien yang berputar di tepi kartu. */
        .gx-card::after {
            content: '';
            position: absolute;
            inset: 0;
            border-radius: inherit;
            padding: 1px;
            pointer-events: none;
            background: conic-gradient(
                from var(--gx-angle),
                transparent 0deg,
                rgb(var(--gx-accent-rgb-1) / 0.85) 55deg,
                rgb(var(--gx-accent-rgb-2) / 0.95) 110deg,
                transparent 190deg,
                transparent 360deg
            );
            -webkit-mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            -webkit-mask-composite: xor;
            mask: linear-gradient(#000 0 0) content-box, linear-gradient(#000 0 0);
            mask-composite: exclude;
            animation: gx-ring 9s linear infinite;
            opacity: 0.85;
        }

        /* Aurora berputar di dalam kartu -- memberi kesan "cawa" di kaca.
         *
         * PENTING: z-index-nya 0, BUKAN -1.
         *
         * `z-index: -1` pada anak dari elemen yang punya `isolation: isolate`
         * berarti anak itu digambar DI BELAKANG latar induknya -- jadi
         * aurora-nya tidak kelihatan sama sekali, tidak ada error, dan
         * kartu terlihat seperti kotak gelap polos.
         *
         * Dengan `z-index: 0`, aurora digambar di atas latar kartu tapi di
         * bawah isinya (isi dibungkus `.gx-content` dengan z-index 1).
         */
        .gx-aurora {
            position: absolute;
            inset: -45%;
            z-index: 0;
            pointer-events: none;
            background: conic-gradient(
                from 0deg,
                rgb(var(--gx-accent-rgb-1) / 0.65),
                rgb(var(--gx-accent-rgb-2) / 0.35),
                transparent 42%,
                rgb(var(--gx-brand-rgb-2) / 0.35),
                rgb(var(--gx-accent-rgb-1) / 0.65)
            );
            filter: blur(58px);
            opacity: 0.5;
            animation: gx-spin 16s linear infinite;
        }

        .gx-content {
            position: relative;
            z-index: 1;
        }

        /* --- Isi --------------------------------------------------------- */

        .gx-code {
            margin: 0;
            font-size: clamp(4rem, 16vw, 7.5rem);
            font-weight: 600;
            line-height: 0.9;
            letter-spacing: -0.05em;
            background-image: linear-gradient(135deg, rgb(var(--gx-accent-rgb-1)) 0%, rgb(var(--gx-accent-rgb-2)) 48%, rgb(var(--gx-accent-rgb-1)) 100%);
            background-size: 220% 220%;
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
            filter: drop-shadow(0 12px 34px rgb(var(--gx-accent-rgb-1) / 0.38));
            animation: gx-gradient 9s ease infinite;
        }

        .gx-title {
            margin: 10px 0 0;
            font-size: clamp(1.3rem, 4.2vw, 1.75rem);
            font-weight: 600;
            line-height: 1.25;
            letter-spacing: -0.015em;
            color: var(--gx-fg);
        }

        .gx-divider {
            width: 56px;
            height: 2px;
            margin: 22px auto 0;
            border-radius: 999px;
            background: linear-gradient(90deg, transparent, rgb(var(--gx-accent-rgb-1) / 0.9), transparent);
        }

        .gx-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            justify-content: center;
            margin-top: 26px;
        }

        .gx-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 44px;
            padding: 11px 20px;
            border-radius: 14px;
            border: 1px solid transparent;
            font-family: inherit;
            font-size: 0.92rem;
            font-weight: 600;
            line-height: 1.2;
            text-decoration: none;
            cursor: pointer;
            transition: transform 0.22s cubic-bezier(0.22, 1, 0.36, 1), box-shadow 0.22s ease, background-color 0.22s ease, border-color 0.22s ease;
        }

        .gx-btn svg {
            width: 18px;
            height: 18px;
            flex: 0 0 auto;
            fill: none;
            stroke: currentColor;
            stroke-width: 1.7;
        }

        .gx-btn:focus-visible {
            outline: 2px solid {{ $errorPage['brand'][0] }};
            outline-offset: 3px;
        }

        .gx-btn--primary {
            color: var(--gx-brand-on);
            background-image: linear-gradient(135deg, var(--gx-brand-1) 0%, var(--gx-brand-2) 100%);
            box-shadow: 0 16px 34px -14px rgb(var(--gx-brand-rgb-1) / 0.85);
        }

        .gx-btn--primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 22px 42px -14px rgb(var(--gx-brand-rgb-1) / 0.95);
        }

        .gx-btn:active {
            transform: translateY(0);
        }

        /* --- Animasi masuk (bertahap) -------------------------------------
         *
         * PENTING: `opacity: 0` TIDAK ditulis di aturan dasar.
         *
         * Kalau aturan dasar yang menaruh `opacity: 0`, lalu animasi GAGAL
         * jalan (browser lama, renderer headless, ekstensi yang memblokir
         * animasi), isinya jadi permanen tak terlihat -- halaman error yang
         * isinya kosong. Dengan `animation-fill-mode: both` dan keyframe
         * yang sendiri menentukan opacity, elemen tetap terlihat kalau
         * animasinya tidak pernah jalan sama sekali.
         */
        .gx-enter {
            animation: gx-rise 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
            animation-delay: var(--gx-delay, 0s);
        }

        @keyframes gx-rise {
            from {
                opacity: 0;
                transform: translateY(18px) scale(0.985);
            }
            to {
                opacity: 1;
                transform: none;
            }
        }

        @keyframes gx-float-a {
            0%, 100% { transform: translate3d(0, 0, 0) scale(1); }
            50% { transform: translate3d(6vw, 5vh, 0) scale(1.12); }
        }

        @keyframes gx-float-b {
            0%, 100% { transform: translate3d(0, 0, 0) scale(1.05); }
            50% { transform: translate3d(-7vw, -6vh, 0) scale(0.94); }
        }

        @keyframes gx-float-c {
            0%, 100% { transform: translate3d(0, 0, 0) scale(0.95); }
            50% { transform: translate3d(-5vw, 7vh, 0) scale(1.15); }
        }

        @keyframes gx-spin {
            to { transform: rotate(360deg); }
        }

        @keyframes gx-ring {
            to { --gx-angle: 360deg; }
        }

        @keyframes gx-gradient {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }

        /* --- Hormati preferensi pengguna --------------------------------- */

        @media (prefers-reduced-motion: reduce) {
            *,
            *::before,
            *::after {
                animation-duration: 0.001ms !important;
                animation-delay: 0s !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.001ms !important;
            }

            .gx-enter {
                opacity: 1 !important;
                transform: none !important;
            }
        }

        @media (prefers-reduced-transparency: reduce) {
            .gx-card {
                background-color: rgb(255 255 255 / 0.98);
                background-image: none;
                -webkit-backdrop-filter: none;
                backdrop-filter: none;
            }

            html.dark .gx-card {
                background-color: rgb(9 9 11 / 0.98);
            }

            .gx-aurora,
            .gx-noise {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .gx-actions {
                flex-direction: column;
            }

            .gx-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="gx-orb gx-orb--1" aria-hidden="true"></div>
    <div class="gx-orb gx-orb--2" aria-hidden="true"></div>
    <div class="gx-orb gx-orb--3" aria-hidden="true"></div>
    <div class="gx-grid" aria-hidden="true"></div>
    <div class="gx-noise" aria-hidden="true"></div>

    <main class="gx-shell" role="main">
        <div class="gx-card">
            <div class="gx-aurora" aria-hidden="true"></div>

            <div class="gx-content">
                <p class="gx-code gx-enter" style="--gx-delay: 0.06s">{{ $errorPage['code'] }}</p>

                <h1 class="gx-title gx-enter" style="--gx-delay: 0.14s">{{ $errorPage['title'] }}</h1>

                <div class="gx-divider gx-enter" style="--gx-delay: 0.2s" aria-hidden="true"></div>

                <div class="gx-actions gx-enter" style="--gx-delay: 0.26s">
                    @foreach ($errorPage['actions'] as $action)
                        @if (($action['reload'] ?? false) === true)
                            <button type="button" class="gx-btn gx-btn--{{ $action['variant'] }}" data-gx-reload>
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">{!! $action['icon'] !!}</svg>
                                <span>{{ $action['label'] }}</span>
                            </button>
                        @else
                            <a href="{{ $action['url'] }}" class="gx-btn gx-btn--{{ $action['variant'] }}">
                                <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">{!! $action['icon'] !!}</svg>
                                <span>{{ $action['label'] }}</span>
                            </a>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    </main>

    <script>
        (function () {
            document.querySelectorAll('[data-gx-reload]').forEach(function (button) {
                button.addEventListener('click', function () {
                    window.location.reload();
                });
            });

            // JARING PENGAMAN untuk animasi masuk.
            //
            // Keyframe "from" berisi opacity 0, jadi selama penundaan (dan
            // selama animasi berjalan) isinya tidak terlihat. Kalau lini masa
            // animasi TIDAK pernah maju -- tab dilatarbelakankan, jendela
            // tidak dirender, atau browser membekukan animasi -- isinya
            // terkunci di opacity 0 dan halaman jadi kosong.
            //
            // Animasi masuk paling lama 0.34s (tundaan terakhir) + 0.7s
            // (durasi) = 1.04s. Setelah 2.5s, paksa semua elemen ke kondisi
            // akhir. Kalau animasinya memang sudah selesai, ini tidak
            // mengubah apa pun.
            var items = document.querySelectorAll('.gx-enter');

            if (! items.length) {
                return;
            }

            window.setTimeout(function () {
                items.forEach(function (element) {
                    element.style.animation = 'none';
                    element.style.opacity = '1';
                    element.style.transform = 'none';
                });
            }, 2500);
        })();
    </script>
</body>
</html>
