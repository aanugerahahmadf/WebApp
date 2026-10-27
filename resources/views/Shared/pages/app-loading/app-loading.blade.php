<!doctype html>
<html lang="{{ str_starts_with(app()->getLocale(), 'id') ? 'id' : 'en' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>{{ __('Please wait') }}</title>
    <style>
        :root { color-scheme: light dark; }
        * { box-sizing: border-box; }
        html, body { width: 100%; height: 100%; margin: 0; }
        body {
            display: grid;
            place-items: center;
            overflow: hidden;
            background: #f8fafc;
            color: #111827;
            font-family: Instrument Sans, Inter, system-ui, sans-serif;
        }
        .app-loading { width: min(90vw, 360px); text-align: center; }
        .app-loading__logo { width: 88px; height: 88px; object-fit: contain; margin-bottom: 24px; }
        .app-loading__title { margin: 0 0 8px; font-size: 20px; font-weight: 650; }
        .app-loading__status { margin: 0; color: #64748b; font-size: 14px; }
        .app-loading__track { height: 6px; margin-top: 24px; overflow: hidden; border-radius: 999px; background: #e2e8f0; }
        .app-loading__bar { width: 0; height: 100%; border-radius: inherit; background: #eab308; transition: width 180ms ease; }
        .app-loading__percent { margin: 10px 0 0; color: #94a3b8; font-size: 12px; }
        @media (prefers-color-scheme: dark) {
            body { background: #0f1115; color: #f8fafc; }
            .app-loading__status { color: #94a3b8; }
            .app-loading__track { background: #272b33; }
        }
    </style>
</head>
<body>
    <main class="app-loading" aria-live="polite">
        <img class="app-loading__logo" src="{{ asset('images/logo.png') }}" alt="">
        <h1 class="app-loading__title">{{ __('Please wait') }}</h1>
        <p class="app-loading__status" id="status">{{ __('Optimizing data...') }}</p>
        <div class="app-loading__track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
            <div class="app-loading__bar" id="bar"></div>
        </div>
        <p class="app-loading__percent"><span id="percent">0</span>%</p>
    </main>
    <script>
        (() => {
            const target = @json($target);
            const bar = document.getElementById('bar');
            const percent = document.getElementById('percent');
            const progress = document.querySelector('[role="progressbar"]');
            const status = document.getElementById('status');
            const statuses = @json([__('Optimizing data...'), __('Preparing application...'), __('Almost ready...')]);
            const started = Date.now();
            const minimum = 900;
            let value = 0;

            const render = () => {
                value = Math.min(92, value + (value < 60 ? 4 : 1));
                bar.style.width = `${value}%`;
                percent.textContent = value;
                progress.setAttribute('aria-valuenow', value);
                status.textContent = statuses[Math.min(2, Math.floor(value / 34))];
            };
            const timer = setInterval(render, 90);

            const finish = () => {
                const wait = Math.max(0, minimum - (Date.now() - started));
                setTimeout(() => {
                    clearInterval(timer);
                    bar.style.width = '100%';
                    percent.textContent = '100';
                    progress.setAttribute('aria-valuenow', '100');
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
