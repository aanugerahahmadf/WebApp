#!/usr/bin/env python3
"""Inventarisasi semua nama ikon yang dipakai di app/ dan resources/views/."""

import os
import re
import collections

ROOT = os.path.join(os.path.dirname(os.path.abspath(__file__)), "..")

PATTERNS = [
    re.compile(r"""->icon\(\s*['"]([a-z0-9\-_]+)['"]"""),
    re.compile(r"""->descriptionIcon\(\s*['"]([a-z0-9\-_]+)['"]"""),
    re.compile(r"""\$?(?:active)?[Nn]avigationIcon\s*=\s*['"]([a-z0-9\-_]+)['"]"""),
    re.compile(r"""\bicon\s*=\s*['"]([a-z0-9\-_]+)['"]"""),
    re.compile(r""":icon\s*=\s*['"]([a-z0-9\-_]+)['"]"""),
    re.compile(r"""icon-alias\s*=\s*['"]([a-z0-9\-_]+)['"]"""),
    re.compile(r"""iconAlias\s*=\s*['"]([a-z0-9\-_]+)['"]"""),
]

counts = collections.Counter()
where = collections.defaultdict(list)

for base in ("app", "resources/views", "config"):
    for dirpath, _dirs, files in os.walk(os.path.join(ROOT, base)):
        for name in files:
            if not name.endswith((".php", ".blade.php")):
                continue
            path = os.path.join(dirpath, name)
            try:
                with open(path, "r", encoding="utf-8", errors="ignore") as fh:
                    text = fh.read()
            except OSError:
                continue
            for pat in PATTERNS:
                for m in pat.finditer(text):
                    icon = m.group(1)
                    if not icon or icon.startswith("panels::"):
                        continue
                    counts[icon] += 1
                    rel = os.path.relpath(path, ROOT).replace("\\", "/")
                    if rel not in where[icon]:
                        where[icon].append(rel)

print(f"DISTINCT ICONS = {len(counts)}")
print()
for icon, n in sorted(counts.items(), key=lambda kv: (-kv[1], kv[0])):
    print(f"  {n:4d}x {icon}")
