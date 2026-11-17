# Tulis preview statis semua ikon ke public/icon-preview.html (tanpa PHP).

from pathlib import Path

icons = sorted(Path('public/images/icons').glob('*.svg'))
cells = '\n'.join(
    f'<div class="cell"><img src="/images/icons/{p.stem}.svg" alt="{p.stem}"><p>{p.stem}</p></div>'
    for p in icons)
small = '\n'.join(
    f'<img src="/images/icons/{p.stem}.svg" title="{p.stem} @24px">'
    for p in icons)

Path('public/icon-preview.html').write_text(
    '''<!DOCTYPE html>
<html lang="id"><head><meta charset="utf-8"><title>Preview Ikon SVG</title>
<style>body{font-family:system-ui,Arial;background:#1e293b;color:#e2e8f0;margin:24px}h1{font-size:16px}
.grid{display:grid;grid-template-columns:repeat(6,1fr);gap:14px}
.cell{background:#0f172a;border:1px solid #334155;border-radius:10px;padding:12px 6px 8px;text-align:center}
.cell img{width:56px;height:56px;display:block;margin:0 auto 8px;background:#f8fafc;border-radius:8px}
.cell p{margin:0;font-size:11px;color:#94a3b8}.row2{display:flex;gap:10px;align-items:center;margin-top:24px}.row2 img{width:24px;height:24px}</style></head>
<body><h1>Preview ikon navigation/menu/halaman (SVG berwarna + animasi)</h1>
''' + f'<div class="grid">{cells}</div>\n<div class="row2">{small}</div></body></html>',
    encoding='utf-8')
print('preview:', len(icons), 'ikon')
