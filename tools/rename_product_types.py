# -*- coding: utf-8 -*-
"""
Second pass of the Product -> Catalog rename: rewrite bare `Product` TYPE
declarations that the first pass missed (it only handled `Product::`).

Verified beforehand that no other class/interface/enum named Product exists in
app/, database/ or tests/, and that nothing imports a foreign Product symbol,
so every remaining bare `Product` in a type position is the renamed model.

Deliberately NOT touched (they are variables, not types):
    foreach ($products as $product)
    ProductResource::getCheckoutWizardSteps(...)

Run:  python tools/rename_product_types.py
"""

import io
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

SKIP_DIRS = {'vendor', 'node_modules', '.git', 'storage', 'bootstrap',
             'public', '.idea', '.vscode', 'exportedData'}

# Ordered, most specific first.
RULES = [
    # ?Product $x / Product $x / Product|int $x  -> parameter or property type
    (re.compile(r'(?<![\\\w$])Product(?=\s*\|\s*\w+\s+\$)'), 'Catalog'),
    (re.compile(r'(?<![\\\w$])Product(?=\s*\[\]\s*\$)'), 'Catalog'),
    (re.compile(r'(?<![\\\w$])Product(?=\s+\$)'), 'Catalog'),
    # iterable<Product>, Collection<int, Product>
    (re.compile(r'(?<![\\\w$])Product(?=>)'), 'Catalog'),
    # return types: ): Product, : ?Product
    (re.compile(r'(?<![\\\w$]):\s*\??Product(?![\\\w])'), ': Catalog'),
]


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
            with io.open(path, 'r', encoding='utf-8') as fh:
                src = fh.read()
        except (UnicodeDecodeError, OSError):
            continue

        if 'Product' not in src:
            continue

        tmp = src
        for pattern, repl in RULES:
            tmp = pattern.sub(repl, tmp)

        if tmp != src:
            with io.open(path, 'w', encoding='utf-8', newline='') as fh:
                fh.write(tmp)
            changed.append(os.path.relpath(path, ROOT))

    print('Files rewritten: %d' % len(changed))
    for rel in sorted(changed):
        print('  ' + rel.replace(chr(92), '/'))


if __name__ == '__main__':
    sys.exit(main())
