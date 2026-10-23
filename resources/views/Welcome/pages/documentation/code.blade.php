<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="{{ asset('css/app-font.css') }}">
    <title>{{ $title }} — Kode Program</title>
    <style>
        :root { color-scheme: dark; --bg: #0b0b0c; --panel: #171719; --line: rgb(255 255 255 / .14); --text: #f4f4f5; --muted: #a1a1aa; --accent: #a5b4fc; }
        * { box-sizing: border-box; }
        body { margin: 0; min-height: 100vh; padding: 2rem; background: radial-gradient(circle at 20% 0%, rgb(79 70 229 / .22), transparent 36rem), var(--bg); color: var(--text); font-family: 'Google Sans', ui-sans-serif, system-ui, sans-serif; }
        .code-page { width: min(100%, 1100px); margin: 0 auto; }
        .back { display: inline-block; margin-bottom: 1.25rem; color: var(--accent); text-decoration: none; }
        .header { display: flex; align-items: center; justify-content: space-between; gap: 1rem; margin-bottom: .75rem; }
        h1 { margin: 0; font-size: 1.5rem; }
        .path { color: var(--muted); font-size: .85rem; }
        pre { overflow: auto; margin: 0; padding: 1.25rem; color: #f5f5f4; background: var(--panel); border: 1px solid var(--line); border-radius: .4rem; line-height: 1.65; font: .9rem/1.65 ui-monospace, SFMono-Regular, Consolas, monospace; user-select: text; }
    </style>
</head>
<body>
    <main class="code-page">
        <a class="back" href="{{ url('/docs') }}">← Kembali ke dokumentasi</a>
        <div class="header">
            <h1>{{ $title }}</h1>
            <span class="path">{{ $path }}</span>
        </div>
        <pre><code>{{ $code }}</code></pre>
    </main>
</body>
</html>
