# -*- coding: utf-8 -*-
"""
Rename Product -> Catalog across the Laravel backend (PHP sources only).

Rules (deliberately conservative, no data migration required):
  * FQCN  App\\Models\\Product\\Product  ->  App\\Models\\Catalog\\Catalog
    (plain literal replace, so the shorter legacy morph alias string
     'App\\Models\\Product' is left untouched on purpose)
  * Bare class reference  Product::  ->  Catalog::
    only when NOT namespace-qualified, so \\Weddingapp\\Product\\Product
    (the generated gRPC message namespace) is kept as-is.

Run:  python tools/rename_product_to_catalog.py
"""

import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

SKIP_DIRS = {
    'vendor', 'node_modules', '.git', 'storage', 'bootstrap',
    'public', '.idea', '.vscode', 'exportedData',
}

OLD_FQCN = 'App' + chr(92) + 'Models' + chr(92) + 'Product' + chr(92) + 'Product'
NEW_FQCN = 'App' + chr(92) + 'Models' + chr(92) + 'Catalog' + chr(92) + 'Catalog'

# Bare `Product::` not preceded by a backslash or word character.
BARE_RE = re.compile(r'(?<![\\\w])Product::')


def php_files():
    for base, dirs, files in os.walk(ROOT):
        dirs[:] = [d for d in dirs if d not in SKIP_DIRS]
        for name in files:
            if name.endswith('.php'):
                yield os.path.join(base, name)


def main():
    changed = []
    for path in php_files():
        try:
            with open(path, 'r', encoding='utf-8') as fh:
                src = fh.read()
        except (UnicodeDecodeError, OSError):
            continue

        if 'Product' not in src:
            continue

        # 1) Rewrite the fully-qualified class name (covers `use` statements).
        tmp = src.replace(OLD_FQCN, NEW_FQCN)

        # 2) Rewrite bare class references.
        tmp = BARE_RE.sub('Catalog::', tmp)

        if tmp != src:
            with open(path, 'w', encoding='utf-8', newline='') as fh:
                fh.write(tmp)
            changed.append(os.path.relpath(path, ROOT))

    print('Files rewritten: %d' % len(changed))
    for rel in sorted(changed):
        print('  ' + rel.replace(chr(92), '/'))


if __name__ == '__main__':
    sys.exit(main())
