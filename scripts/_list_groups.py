# Cetak daftar GROUPS + SEMANTIC_FILES dari generator (untuk mapping MSymbol).

import re
from pathlib import Path

text = Path('scripts/generate-icons.py').read_text(encoding='utf-8')

def dict_keys(name: str, source: str) -> list[str]:
    start = source.index(f'{name} = {{')
    depth = 0
    body = source[start:]
    keys = []
    # potong sampai kurung tutup seimbang
    for i, ch in enumerate(body.split('\n')):
        if ch.startswith(name) is False and (ch.strip().startswith("'") or ch.strip().startswith('"') or ch.strip().startswith('#')):
            m = re.match(r"^\s*'?([a-z0-9_\-]+)'?", ch)
            if m:
                keys.append(m.group(1))
    out = []
    seen = set()
    for k in keys:
        if k not in seen:
            seen.add(k)
            out.append(k)
    return out

groups_block = text[text.index('GROUPS = {'):text.index('SEMANTIC_FILES = {')]
semantic_block = text[text.index('SEMANTIC_FILES = {'):text.index('# Palet') | -1]

gkeys = [m.group(1) for m in re.finditer(r"'([a-z0-9_\-]+)'\s*:", groups_block)]
skeys = [m.group(1) for m in re.finditer(r"'([a-z0-9_\-]+)'\s*:", semantic_block)]

print('GROUPS', len(gkeys))
print(' '.join(dict.fromkeys(gkeys)))
print()
print('SEMANTIC', len(skeys))
print(' '.join(dict.fromkeys(skeys)))
