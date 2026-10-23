<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="{{ asset('css/app-font.css') }}">
    <title>{{ $documents[$selected]['title'] }} — Dokumentasi</title>
    <style>
        :root {
            color-scheme: light;
            --docs-bg: #eef1f9;
            --docs-surface: rgb(255 255 255 / .20);
            --docs-ring: rgb(0 0 0 / .12);
            --docs-tint: rgb(255 255 255 / .50);
            --docs-box: rgb(79 70 229 / .14);
            --docs-text: #172033;
            --docs-muted: #64748b;
            --docs-accent: rgb(202 138 4);
            --docs-code: rgb(255 255 255 / .42);
        }
        .dark {
            color-scheme: dark;
            --docs-bg: #06080f;
            --docs-surface: rgb(12 12 15 / .20);
            --docs-ring: rgb(255 255 255 / .15);
            --docs-tint: rgb(0 0 0 / .50);
            --docs-box: rgb(79 70 229 / .14);
            --docs-text: #e5e7eb;
            --docs-muted: #a1a1aa;
            --docs-accent: rgb(250 204 21);
            --docs-code: rgb(15 23 42 / .52);
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: radial-gradient(circle at 15% 0%, rgb(99 102 241 / .16), transparent 34rem), var(--docs-bg); color: var(--docs-text); font-family: 'Google Sans', ui-sans-serif, system-ui, sans-serif; }
        a { color: var(--docs-accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .docs-shell { display: grid; grid-template-columns: 280px minmax(0, 1fr); min-height: 100vh; }
        .docs-sidebar { position: sticky; top: 0; height: 100vh; overflow-y: auto; padding: 1.5rem 1rem; background-color: var(--docs-surface); background-image: linear-gradient(var(--docs-tint), var(--docs-tint)); border-right: 1px solid var(--docs-ring); backdrop-filter: blur(15px) saturate(180%); -webkit-backdrop-filter: blur(15px) saturate(180%); }
        .brand { display: flex; align-items: center; gap: .65rem; margin: 0 .6rem 1.5rem; color: var(--docs-text); font-weight: 700; font-size: 1.1rem; }
        .brand:hover { text-decoration: none; }
        .brand-mark { display: grid; place-items: center; width: 2rem; height: 2rem; border-radius: .7rem; background: var(--docs-box); color: var(--docs-accent); }
        .brand-mark svg { width: 1.15rem; height: 1.15rem; stroke: currentColor; }
        .search { width: 100%; padding: .7rem .8rem; margin-bottom: 1.25rem; color: var(--docs-text); background: var(--docs-surface); border: 1px solid var(--docs-ring); border-radius: .55rem; outline: none; font: inherit; }
        .search::placeholder { color: var(--docs-muted); }
        .search:focus { border-color: var(--docs-accent); }
        .group { margin: 1.15rem .35rem .45rem; color: var(--docs-muted); font-size: .7rem; font-weight: 700; letter-spacing: .08em; text-transform: uppercase; }
        .nav-link { display: block; padding: .48rem .65rem; border-radius: .55rem; color: var(--docs-muted); font-size: .88rem; }
        .nav-link:hover { color: var(--docs-text); background: rgb(0 0 0 / .05); text-decoration: none; }
        .dark .nav-link:hover { background: rgb(255 255 255 / .05); }
        .nav-link.active { color: var(--docs-accent); background: var(--docs-box); text-decoration: none; }
        .docs-main { width: min(100%, 980px); padding: 3.5rem clamp(1.25rem, 5vw, 5rem); }
        .eyebrow { color: var(--docs-accent); font-size: .78rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
        .markdown { line-height: 1.75; font-size: 1rem; }
        .markdown h1 { margin: .35rem 0 1.5rem; color: var(--docs-text); font-size: clamp(2rem, 5vw, 3.25rem); line-height: 1.1; }
        .markdown h2 { margin-top: 2.7rem; padding-bottom: .45rem; color: var(--docs-text); border-bottom: 1px solid var(--docs-ring); }
        .markdown h3 { margin-top: 2rem; color: var(--docs-text); }
        .markdown p, .markdown ul, .markdown ol { color: var(--docs-text); }
        .markdown blockquote { margin: 1.25rem 0; padding: .7rem 1rem; color: var(--docs-muted); background: var(--docs-surface); border-left: 3px solid var(--docs-accent); border-radius: .25rem; }
        .markdown code { padding: .15rem .35rem; color: var(--docs-accent); background: var(--docs-code); border-radius: .3rem; }
        .markdown pre { overflow-x: auto; padding: 1rem; background: var(--docs-code); border: 1px solid var(--docs-ring); border-radius: .8rem; }
        .markdown pre code { padding: 0; background: none; color: var(--docs-text); }
        .markdown table { display: block; overflow-x: auto; width: 100%; border-collapse: collapse; }
        .markdown th, .markdown td { padding: .65rem .8rem; text-align: left; border: 1px solid var(--docs-ring); }
        .markdown th { color: var(--docs-text); background: var(--docs-surface); }
        .markdown img { max-width: 100%; height: auto; border-radius: .75rem; }
        .back { display: inline-block; margin-bottom: 2rem; color: var(--docs-muted); font-size: .9rem; }
        @media (max-width: 760px) { .docs-shell { display: block; } .docs-sidebar { position: static; height: auto; max-height: 45vh; border-right: 0; border-bottom: 1px solid var(--docs-ring); } .docs-main { padding-top: 2rem; } }
    </style>
</head>
<body>
<div class="docs-shell">
    <aside class="docs-sidebar">
        <a class="brand" href="{{ route('docs.index') }}">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 5.5A2.5 2.5 0 0 1 6.5 3H20v16H6.5A2.5 2.5 0 0 0 4 21.5v-16Z"/>
                    <path d="M4 21.5A2.5 2.5 0 0 1 6.5 19H20M8 7h8M8 11h8"/>
                </svg>
            </span>
            Dokumentasi
        </a>
        <input class="search" type="search" placeholder="Cari dokumentasi…" aria-label="Cari dokumentasi" data-doc-search>
        <nav aria-label="Daftar dokumentasi">
            @foreach ($groups as $group)
                <div class="group">{{ $group['label'] }}</div>
                @foreach ($group['items'] as $item)
                    <a class="nav-link {{ $selected === $item['key'] ? 'active' : '' }}" href="{{ route('docs.show', ['path' => $item['key']]) }}" data-doc-link>{{ $item['title'] }}</a>
                @endforeach
            @endforeach
        </nav>
    </aside>
    <main class="docs-main">
        <a class="back" href="{{ url('/home') }}">← Kembali ke aplikasi</a>
        <div class="eyebrow">Wedding Flower Decorations</div>
        <article class="markdown">{!! $document !!}</article>
    </main>
</div>
<script>
    document.querySelector('[data-doc-search]')?.addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        document.querySelectorAll('[data-doc-link]').forEach((link) => {
            link.hidden = query !== '' && !link.textContent.toLowerCase().includes(query);
        });
    });
</script>
<script>
    (() => {
        const stored = localStorage.getItem('theme') || 'system';
        const dark = stored === 'dark' || (stored === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', dark);
    })();
</script>
</body>
</html>
