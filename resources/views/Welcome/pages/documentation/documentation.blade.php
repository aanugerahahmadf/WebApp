<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $documents[$selected]['title'] }} — Dokumentasi</title>
    <style>
        :root { color-scheme: dark; --bg: #020617; --surface: rgba(15, 23, 42, .72); --line: rgba(148, 163, 184, .22); --text: #e2e8f0; --muted: #94a3b8; --accent: #fbbf24; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: radial-gradient(circle at 15% 0%, #172554 0, transparent 34rem), var(--bg); color: var(--text); font-family: Inter, ui-sans-serif, system-ui, sans-serif; }
        a { color: #fcd34d; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .docs-shell { display: grid; grid-template-columns: 280px minmax(0, 1fr); min-height: 100vh; }
        .docs-sidebar { position: sticky; top: 0; height: 100vh; overflow-y: auto; padding: 1.5rem 1rem; background: rgba(2, 6, 23, .72); border-right: 1px solid var(--line); backdrop-filter: blur(15px) saturate(180%); }
        .brand { display: flex; align-items: center; gap: .65rem; margin: 0 .6rem 1.5rem; color: #fff; font-weight: 800; font-size: 1.1rem; }
        .brand-mark { display: grid; place-items: center; width: 2rem; height: 2rem; border-radius: .7rem; background: rgba(251, 191, 36, .18); color: var(--accent); }
        .search { width: 100%; padding: .7rem .8rem; margin-bottom: 1.25rem; color: var(--text); background: rgba(15, 23, 42, .65); border: 1px solid var(--line); border-radius: .7rem; outline: none; }
        .search:focus { border-color: rgba(251, 191, 36, .65); }
        .group { margin: 1.15rem .35rem .45rem; color: var(--muted); font-size: .7rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .nav-link { display: block; padding: .48rem .65rem; border-radius: .55rem; color: var(--muted); font-size: .88rem; }
        .nav-link:hover, .nav-link.active { color: #fff; background: rgba(251, 191, 36, .13); text-decoration: none; }
        .docs-main { width: min(100%, 980px); padding: 3.5rem clamp(1.25rem, 5vw, 5rem); }
        .eyebrow { color: var(--accent); font-size: .78rem; font-weight: 800; letter-spacing: .1em; text-transform: uppercase; }
        .markdown { line-height: 1.75; font-size: 1rem; }
        .markdown h1 { margin: .35rem 0 1.5rem; color: #fff; font-size: clamp(2rem, 5vw, 3.25rem); line-height: 1.1; }
        .markdown h2 { margin-top: 2.7rem; padding-bottom: .45rem; color: #fff; border-bottom: 1px solid var(--line); }
        .markdown h3 { margin-top: 2rem; color: #f8fafc; }
        .markdown p, .markdown ul, .markdown ol { color: var(--text); }
        .markdown blockquote { margin: 1.25rem 0; padding: .7rem 1rem; color: #cbd5e1; background: rgba(30, 41, 59, .5); border-left: 3px solid var(--accent); border-radius: .25rem; }
        .markdown code { padding: .15rem .35rem; color: #fde68a; background: rgba(15, 23, 42, .8); border-radius: .3rem; }
        .markdown pre { overflow-x: auto; padding: 1rem; background: rgba(2, 6, 23, .8); border: 1px solid var(--line); border-radius: .8rem; }
        .markdown pre code { padding: 0; background: none; color: #cbd5e1; }
        .markdown table { display: block; overflow-x: auto; width: 100%; border-collapse: collapse; }
        .markdown th, .markdown td { padding: .65rem .8rem; text-align: left; border: 1px solid var(--line); }
        .markdown th { color: #fff; background: rgba(51, 65, 85, .55); }
        .markdown img { max-width: 100%; height: auto; border-radius: .75rem; }
        .back { display: inline-block; margin-bottom: 2rem; color: var(--muted); font-size: .9rem; }
        @media (max-width: 760px) { .docs-shell { display: block; } .docs-sidebar { position: static; height: auto; max-height: 45vh; border-right: 0; border-bottom: 1px solid var(--line); } .docs-main { padding-top: 2rem; } }
    </style>
</head>
<body>
<div class="docs-shell">
    <aside class="docs-sidebar">
        <a class="brand" href="{{ route('docs.index') }}"><span class="brand-mark">✦</span> Dokumentasi</a>
        <input class="search" type="search" placeholder="Cari dokumentasi…" aria-label="Cari dokumentasi" data-doc-search>
        <nav aria-label="Daftar dokumentasi">
            @foreach ($documents as $key => $item)
                @if ($loop->first || $documents[$loop->index - 1]['category'] !== $item['category'])
                    <div class="group">{{ $item['category'] }}</div>
                @endif
                <a class="nav-link {{ $selected === $key ? 'active' : '' }}" href="{{ route('docs.show', ['path' => $key]) }}" data-doc-link>{{ $item['title'] }}</a>
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
</body>
</html>
