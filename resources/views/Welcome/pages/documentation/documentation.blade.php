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
            --docs-group: #334155;
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
            --docs-group: #f4f4f5;
            --docs-accent: rgb(250 204 21);
            --docs-code: rgb(15 23 42 / .52);
        }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; background: radial-gradient(circle at 15% 0%, rgb(99 102 241 / .16), transparent 34rem), var(--docs-bg); color: var(--docs-text); font-family: 'Google Sans', ui-sans-serif, system-ui, sans-serif; }
        a { color: var(--docs-accent); text-decoration: none; }
        a:hover { text-decoration: underline; }
        .docs-shell { display: grid; grid-template-columns: 280px minmax(0, 1fr); min-height: 100vh; transition: grid-template-columns .2s ease; }
        .docs-shell.sidebar-collapsed { grid-template-columns: 76px minmax(0, 1fr); }
        .docs-sidebar { position: sticky; top: 0; height: 100vh; overflow-y: auto; scrollbar-width: none; padding: 1.5rem 1rem; background-color: var(--docs-surface); background-image: linear-gradient(var(--docs-tint), var(--docs-tint)); border-right: 1px solid var(--docs-ring); backdrop-filter: blur(15px) saturate(180%); -webkit-backdrop-filter: blur(15px) saturate(180%); }
        .docs-sidebar::-webkit-scrollbar { display: none; }
        .brand { display: flex; align-items: center; gap: .65rem; margin: 0 .6rem 1.5rem; color: var(--docs-text); font-weight: 700; font-size: 1.1rem; }
        .brand:hover { text-decoration: none; }
        .brand-mark { display: grid; place-items: center; width: 2rem; height: 2rem; border-radius: .7rem; background: var(--docs-box); color: var(--docs-accent); }
        .brand-mark svg { width: 1.15rem; height: 1.15rem; stroke: currentColor; }
        .brand-label, .collapse-label { transition: opacity .15s ease; }
        .sidebar-tools { display: flex; align-items: center; justify-content: space-between; gap: .5rem; margin: 0 .35rem 1rem; }
        .collapse-button { display: inline-grid; place-items: center; width: 2rem; height: 2rem; flex: 0 0 2rem; padding: 0; color: var(--docs-muted); background: transparent; border: 1px solid transparent; border-radius: .55rem; cursor: pointer; }
        .collapse-button:hover { color: var(--docs-text); background: rgb(0 0 0 / .05); border-color: var(--docs-ring); }
        .dark .collapse-button:hover { background: rgb(255 255 255 / .05); }
        .collapse-button svg { width: 1rem; height: 1rem; stroke: currentColor; }
        .search { width: 100%; padding: .7rem .8rem; margin-bottom: 1.25rem; color: var(--docs-text); background: var(--docs-surface); border: 1px solid var(--docs-ring); border-radius: .55rem; outline: none; font: inherit; }
        .search::placeholder { color: var(--docs-muted); }
        .search:focus { border-color: var(--docs-accent); }
        .group { display: flex; align-items: center; gap: .4rem; margin: 1.15rem .35rem .45rem; color: var(--docs-group); font-size: .72rem; font-weight: 800; letter-spacing: .08em; text-transform: uppercase; }
        .group svg { width: .85rem; height: .85rem; flex: 0 0 .85rem; stroke: currentColor; }
        .nav-link { display: block; padding: .48rem .65rem; border-radius: .55rem; color: var(--docs-muted); font-size: .88rem; }
        .nav-link:hover { color: var(--docs-text); background: rgb(0 0 0 / .05); text-decoration: none; }
        .dark .nav-link:hover { background: rgb(255 255 255 / .05); }
        .nav-link.active { color: var(--docs-accent); background: var(--docs-box); text-decoration: none; }
        .sidebar-collapsed .brand { justify-content: center; margin-left: 0; margin-right: 0; }
        .sidebar-collapsed .brand-label, .sidebar-collapsed .group, .sidebar-collapsed .search, .sidebar-collapsed .collapse-label { display: none; }
        .sidebar-collapsed .sidebar-tools { justify-content: center; margin-left: 0; margin-right: 0; }
        .sidebar-collapsed .nav-link { overflow: hidden; padding-left: .65rem; padding-right: .65rem; white-space: nowrap; text-indent: -999px; }
        .sidebar-collapsed .collapse-button svg { transform: rotate(180deg); }
        .docs-main { width: min(100%, 980px); padding: 3.5rem clamp(1.25rem, 5vw, 5rem); }
        .eyebrow-row { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: .75rem; }
        .eyebrow { color: var(--docs-accent); font-size: .78rem; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; }
        .markdown { line-height: 1.75; font-size: 1rem; text-align: justify; }
        .markdown, .markdown * { user-select: text; -webkit-user-select: text; }
        .markdown h1 { margin: .35rem 0 1.5rem; color: var(--docs-text); font-size: clamp(2rem, 5vw, 3.25rem); line-height: 1.1; }
        .markdown h2 { margin-top: 2.7rem; padding-bottom: .45rem; color: var(--docs-text); border-bottom: 1px solid var(--docs-ring); }
        .markdown h3 { margin-top: 2rem; color: var(--docs-text); }
        .markdown h1, .markdown h2, .markdown h3, .markdown pre, .markdown code { text-align: left; }
        .markdown p, .markdown ul, .markdown ol { color: var(--docs-text); }
        .markdown blockquote { margin: 1.25rem 0; padding: .7rem 1rem; color: var(--docs-muted); background: var(--docs-surface); border-left: 3px solid var(--docs-accent); border-radius: .25rem; }
        .markdown code { padding: .15rem .35rem; color: var(--docs-accent); background: var(--docs-code); border-radius: .3rem; }
        .markdown pre { overflow-x: auto; margin: 0; padding: 1.1rem 1rem 1.25rem; background: #111; border: 0; border-radius: 0 0 .4rem .4rem; }
        .code-wrap { position: relative; overflow: hidden; margin: 1.35rem 0; background: #111; border: 1px solid rgb(255 255 255 / .12); border-radius: .4rem; box-shadow: 0 12px 30px rgb(0 0 0 / .14); }
        .code-header { display: flex; align-items: center; justify-content: space-between; min-height: 2.65rem; padding: 0 .75rem; color: #a8a29e; background: #292524; border-bottom: 1px solid rgb(255 255 255 / .14); font-size: .84rem; }
        .code-title { display: inline-flex; align-items: center; gap: .65rem; margin: 0 auto; transform: translateX(1.8rem); }
        .code-dots { display: inline-flex; gap: .35rem; }
        .code-dots i { display: block; width: .62rem; height: .62rem; border-radius: 999px; }
        .code-dots i:nth-child(1) { background: #fbbf24; }
        .code-dots i:nth-child(2) { background: #fda4af; }
        .code-dots i:nth-child(3) { background: #93c5fd; }
        .copy-button { display: inline-flex; align-items: center; gap: .35rem; padding: .35rem .55rem; color: #d6d3d1; background: transparent; border: 1px solid transparent; border-radius: .4rem; cursor: pointer; font: inherit; font-size: .76rem; }
        .copy-button:hover { color: var(--docs-text); background: var(--docs-box); }
        .code-header .copy-button { color: #d6d3d1; }
        .code-header .copy-button:hover { color: #fff; background: rgb(255 255 255 / .1); }
        .copy-button svg { width: .9rem; height: .9rem; stroke: currentColor; }
        .markdown pre code { display: block; padding: 0; background: none; color: #f5f5f4; font-size: .9rem; line-height: 1.65; }
        .code-line { display: grid; grid-template-columns: 2.25rem minmax(max-content, 1fr); min-width: max-content; }
        .code-line-number { padding-right: .75rem; color: #57534e; text-align: right; user-select: none; }
        .code-line-content { white-space: pre; }
        .markdown table { display: block; overflow-x: auto; width: 100%; border-collapse: collapse; }
        .markdown th, .markdown td { padding: .65rem .8rem; text-align: left; border: 1px solid var(--docs-ring); }
        .markdown th { color: var(--docs-text); background: var(--docs-surface); }
        .markdown img { max-width: 100%; height: auto; border-radius: .75rem; }
        .back { display: inline-block; margin-bottom: 2rem; color: var(--docs-muted); font-size: .9rem; }
        @media (max-width: 760px) { .docs-shell, .docs-shell.sidebar-collapsed { display: block; } .docs-sidebar { position: static; height: auto; max-height: none; border-right: 0; border-bottom: 1px solid var(--docs-ring); } .docs-shell.sidebar-collapsed .docs-sidebar { height: 4.75rem; overflow: hidden; } .docs-main { padding-top: 2rem; } .eyebrow-row { align-items: flex-start; flex-direction: column; } }
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
            <span class="brand-label">Dokumentasi</span>
        </a>
        <div class="sidebar-tools">
            <input class="search" type="search" placeholder="Cari dokumentasi…" aria-label="Cari dokumentasi" data-doc-search>
            <button class="collapse-button" type="button" aria-label="Ciutkan sidebar" aria-expanded="true" data-sidebar-toggle>
                <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m14 6-6 6 6 6"/><path d="M20 6l-6 6 6 6"/></svg>
            </button>
        </div>
        <nav aria-label="Daftar dokumentasi">
            @foreach ($groups as $group)
                <div class="group">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 6.5A1.5 1.5 0 0 1 4.5 5h5l2 2H19.5A1.5 1.5 0 0 1 21 8.5v9a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 17.5v-11Z"/>
                    </svg>
                    <span>{{ $group['label'] }}</span>
                </div>
                @foreach ($group['items'] as $item)
                    <a class="nav-link {{ $selected === $item['key'] ? 'active' : '' }}" href="{{ route('docs.show', ['path' => $item['key']]) }}" data-doc-link>{{ $item['title'] }}</a>
                @endforeach
            @endforeach
        </nav>
    </aside>
        <main class="docs-main">
            <a class="back" href="{{ url('/home') }}">← Kembali ke aplikasi</a>
        <div class="eyebrow-row">
            <div class="eyebrow">Wedding Flower Decorations</div>
        </div>
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

    const copyText = async (text, button) => {
        try {
            await navigator.clipboard.writeText(text);
            const label = button.querySelector('span');
            if (label) {
                const previous = label.textContent;
                label.textContent = 'Tersalin';
                setTimeout(() => { label.textContent = previous; }, 1400);
            }
        } catch {
            const area = document.createElement('textarea');
            area.value = text;
            document.body.appendChild(area);
            area.select();
            document.execCommand('copy');
            area.remove();
        }
    };

    document.querySelectorAll('.markdown pre').forEach((pre) => {
        const code = pre.querySelector('code');
        const source = code?.textContent || pre.textContent || '';
        const wrapper = document.createElement('div');
        wrapper.className = 'code-wrap';
        pre.parentNode.insertBefore(wrapper, pre);
        const header = document.createElement('div');
        header.className = 'code-header';
        const dots = document.createElement('span');
        dots.className = 'code-dots';
        dots.innerHTML = '<i></i><i></i><i></i>';
        const title = document.createElement('span');
        title.className = 'code-title';
        const language = code?.className.match(/language-([\w-]+)/)?.[1] || 'text';
        title.textContent = language === 'php' ? 'PHP' : language === 'bash' || language === 'shell' ? 'Terminal' : language.toUpperCase();
        header.appendChild(dots);
        header.appendChild(title);
        wrapper.appendChild(header);

        if (code) {
            const lines = source.replace(/\n$/, '').split('\n');
            code.innerHTML = lines.map((line, index) => {
                const number = `<span class="code-line-number">${index + 1}</span>`;
                const content = document.createElement('span');
                content.className = 'code-line-content';
                content.textContent = line || ' ';
                return `<span class="code-line">${number}${content.outerHTML}</span>`;
            }).join('');
        }

        wrapper.appendChild(pre);
        const button = document.createElement('button');
        button.className = 'copy-button';
        button.type = 'button';
        button.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg><span>Salin kode</span>';
        button.addEventListener('click', () => copyText(source, button));
        header.appendChild(button);
    });
</script>
<script>
    (() => {
        const shell = document.querySelector('.docs-shell');
        const toggle = document.querySelector('[data-sidebar-toggle]');
        const collapsed = localStorage.getItem('docs-sidebar-collapsed') === '1';
        shell?.classList.toggle('sidebar-collapsed', collapsed);
        toggle?.setAttribute('aria-expanded', String(!collapsed));
        toggle?.setAttribute('aria-label', collapsed ? 'Buka sidebar' : 'Ciutkan sidebar');
        toggle?.addEventListener('click', () => {
            const next = shell.classList.toggle('sidebar-collapsed');
            localStorage.setItem('docs-sidebar-collapsed', next ? '1' : '0');
            toggle.setAttribute('aria-expanded', String(!next));
            toggle.setAttribute('aria-label', next ? 'Buka sidebar' : 'Ciutkan sidebar');
        });

        const stored = localStorage.getItem('theme') || 'system';
        const dark = stored === 'dark' || (stored === 'system' && matchMedia('(prefers-color-scheme: dark)').matches);
        document.documentElement.classList.toggle('dark', dark);
    })();
</script>
</body>
</html>
