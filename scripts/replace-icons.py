#!/usr/bin/env python3
"""Pengganti nama ikon -> file SVG berwarna di app/, config/, resources/views/.

Aturan yang dipatuhi:

  1. Hanya literal yang di-apit kutip SATU-PERSATU cocok penuh:
     `(?<=['"])heroicon-s-heart(?=['"])` -- sehingga `BRI-External-Id`
     (BriService) dan kunci JSON lain tidak tersapu.
  2. CHEVRON TIDAK DIUBAH. Kunci yang mengandung `chevron` tetap Heroicon --
     itu keputusan eksplisit pengguna (panah dropdown/jempol halaman dst.
     tetap abu-abu bawaan Filament).
  3. Yang sudah berupa path (`/images/icons/...`) dilewati otomatis, karena
     namanya tidak pernah jadi kunci peta.

Peta sumber: app/Support/AppIcon/icon-map.php (dihasilkan generator).
"""

import re
from pathlib import Path

HERE = Path(__file__).resolve().parent
ROOT = HERE.parent
MAP_FILE = ROOT / 'app' / 'Support' / 'AppIcon' / 'icon-map.php'

# baca peta dari PHP `return [ 'nama' => 'file', ... ]`
php = MAP_FILE.read_text(encoding='utf-8')
map_pairs = re.findall(r"'([^']+)' => '([^']+)'", php)
ICON_MAP = dict(map_pairs)

# chevron TIDAK dikecualikan lagi: SEMUA ikon (termasuk panah pagination,
# dropdown, navigator) memakai vektor dari fonts.google.com/icons.
SCOPES = [
    ROOT / 'app',
    ROOT / 'config',
    ROOT / 'resources' / 'views',
    ROOT / 'routes',
]

EXTENSIONS = {'.php'}

# file yang TIDAK boleh tersapu
SKIP = {MAP_FILE}

pattern = re.compile(
    "(?<=['\"])(" + '|'.join(re.escape(name) for name in sorted(ICON_MAP, key=len, reverse=True)) + ")(?=['\"])")


def sub_icon(match: re.Match) -> str:
    name = match.group(1)
    return f'/images/icons/{ICON_MAP[name]}.svg'


replaced_total = 0
files_touched = 0

for scope in SCOPES:
    for path in scope.rglob('*'):
        if path.suffix not in EXTENSIONS or not path.is_file() or path in SKIP:
            continue
        text = path.read_text(encoding='utf-8', errors='ignore')
        new_text, count = pattern.subn(sub_icon, text)

        # Tag komponen Blade <x-heroicon-o-moon class="..."> -> <img src=...>
        # (komponen itu milik blade-icons; ikonnya monokrom).
        tag_pattern = re.compile(
            r'<x-(' + '|'.join(re.escape(name) for name in sorted(ICON_MAP, key=len, reverse=True))
            + r')((?:\s[^<>]*)?/?>)')
        tag_text, tag_count = tag_pattern.subn(
            lambda m: f'<img src="/images/icons/{ICON_MAP[m.group(1)]}.svg"{m.group(2)}', new_text)
        if tag_count:
            count += tag_count
            new_text = tag_text

        if count:
            path.write_text(new_text, encoding='utf-8')
            files_touched += 1
            replaced_total += count

print(f'file diubah     : {files_touched}')
print(f'literal diganti : {replaced_total}')
