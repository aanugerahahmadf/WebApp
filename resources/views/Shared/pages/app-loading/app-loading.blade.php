<!doctype html>
<html lang="{{ str_starts_with(app()->getLocale(), 'id') ? 'id' : 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ __('Please wait') }}</title>
    <style>
        :root { color-scheme: dark; }
        * { box-sizing: border-box; }
        html, body { width: 100%; height: 100%; margin: 0; }
        body {
            display: grid;
            place-items: center;
            overflow: hidden;
            background: #080b12;
            color: #f8fafc;
            font-family: Instrument Sans, Inter, system-ui, -apple-system, sans-serif;
        }
        .app-loading { width: min(100% - 12px, 620px); }
        .app-loading__card {
            overflow: hidden;
            border: 1px solid #293244;
            border-radius: 16px;
            background: #111827;
            box-shadow: 0 20px 60px rgba(0, 0, 0, .25);
        }
        .app-loading__heading { padding: 22px 24px 20px; }
        .app-loading__title { margin: 0 0 6px; font-size: 20px; font-weight: 700; letter-spacing: -.01em; }
        .app-loading__status { margin: 0; color: #8491a8; font-size: 14px; }
        .app-loading__items { border-top: 1px solid #293244; }
        .app-loading__item { display: flex; align-items: center; gap: 16px; min-height: 76px; padding: 14px 24px; border-bottom: 1px solid #293244; }
        .app-loading__item:last-child { border-bottom: 0; }
        .app-loading__icon { display: block; flex: 0 0 32px; width: 32px; height: 32px; object-fit: contain; }
        .app-loading__copy { min-width: 0; flex: 1; }
        .app-loading__label { margin: 0 0 3px; font-size: 16px; font-weight: 650; }
        .app-loading__description { margin: 0; color: #8491a8; font-size: 14px; line-height: 1.35; }
        .app-loading__chevron { width: 18px; height: 18px; object-fit: contain; opacity: .95; }
        @media (max-width: 480px) {
            .app-loading { width: calc(100% - 12px); }
            .app-loading__heading, .app-loading__item { padding-left: 18px; padding-right: 18px; }
            .app-loading__description { font-size: 13px; }
        }
    </style>
</head>
<body>
    <main class="app-loading" aria-live="polite">
        <section class="app-loading__card">
            <header class="app-loading__heading">
                <h1 class="app-loading__title">{{ __('Please wait') }}</h1>
                <p class="app-loading__status" id="status">{{ __('Optimizing data...') }}</p>
            </header>
            <div class="app-loading__items">
                <div class="app-loading__item">
                    <img class="app-loading__icon" src="{{ asset('images/icons/maintenance.svg') }}" alt="">
                    <div class="app-loading__copy"><p class="app-loading__label">{{ __('Preparing application...') }}</p><p class="app-loading__description">{{ __('Getting everything ready for you.') }}</p></div>
                    <img class="app-loading__chevron" src="{{ asset('images/icons/chevron_right.svg') }}" alt="">
                </div>
                <div class="app-loading__item">
                    <img class="app-loading__icon" src="{{ asset('images/icons/sparkles.svg') }}" alt="">
                    <div class="app-loading__copy"><p class="app-loading__label">{{ __('Optimizing data...') }}</p><p class="app-loading__description">{{ __('Loading the latest information securely.') }}</p></div>
                    <img class="app-loading__chevron" src="{{ asset('images/icons/chevron_right.svg') }}" alt="">
                </div>
                <div class="app-loading__item">
                    <img class="app-loading__icon" src="{{ asset('images/icons/heart.svg') }}" alt="">
                    <div class="app-loading__copy"><p class="app-loading__label">{{ __('Almost ready...') }}</p><p class="app-loading__description">{{ __('Your application will open shortly.') }}</p></div>
                    <img class="app-loading__chevron" src="{{ asset('images/icons/chevron_right.svg') }}" alt="">
                </div>
            </div>
        </div>
        </section>
    </main>
    <script>
        (() => {
            const target = @json($target);
            const status = document.getElementById('status');
            const statuses = @json([__('Optimizing data...'), __('Preparing application...'), __('Almost ready...')]);
            const started = Date.now();
            const minimum = 900;
            let value = 0;

            const render = () => {
                value = Math.min(92, value + (value < 60 ? 4 : 1));
                status.textContent = statuses[Math.min(2, Math.floor(value / 34))];
            };
            const timer = setInterval(render, 90);

            const finish = () => {
                const wait = Math.max(0, minimum - (Date.now() - started));
                setTimeout(() => {
                    clearInterval(timer);
                    status.textContent = statuses[2];
                    const url = new URL(target, window.location.origin);
                    url.searchParams.set('__shell', 'native');
                    window.location.replace(url.toString());
                }, wait);
            };

            // Warm the first page's HTML/cache while the user sees progress.
            fetch(target, { credentials: 'include', cache: 'no-store' })
                .catch(() => null)
                .finally(finish);
        })();
    </script>
</body>
</html>
