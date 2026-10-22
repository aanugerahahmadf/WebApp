# Kumpulkan semua alias ikon internal Filament (`FilamentIcon::resolve('x::y')`)
# di vendor, supaya semua bisa ditimpa dengan aset berwarna kita.

import re
from pathlib import Path

ROOT = Path('vendor/filament')
pattern = re.compile(r"""FilamentIcon::resolve\(\s*['"]([\w]+::[\w\-\.]+)['"]""")

found = set()

for p in ROOT.rglob('*.php'):
    try:
        text = p.read_text(encoding='utf-8', errors='ignore')
    except Exception:
        continue
    found.update(match.group(1) for match in pattern.finditer(text))

Path('scripts/alias-keys.txt').write_text('\n'.join(sorted(found)), encoding='utf-8')
print('total', len(found))
