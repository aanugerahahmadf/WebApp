# -*- coding: utf-8 -*-
"""
Corrective pass after rename_product_files_to_catalog.py.

That script was too aggressive in two places:

1. It rewrote the FOREIGN KEY literal `product_id` -> `catalog_id`.
   The physical columns on carts / orders / reviews / wishlists are still
   named `product_id` (no data migration was performed), so every one of those
   references has to go back to `product_id`.

2. It rewrote the `product()` backward-compatibility alias into a second
   `catalog()` method (duplicate declaration + infinite recursion).

The ONLY place where `catalog_id` is correct is the new Package <-> Catalog
tier link (packages.catalog_id), so those files are excluded from the revert.

Run:  python tools/fix_catalog_id_regression.py
"""

import io
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

SKIP_DIRS = {'vendor', 'node_modules', '.git', 'storage', 'bootstrap', 'public',
             '.idea', '.vscode', 'exportedData'}

SCAN_ROOTS = ['app', 'resources', 'database', 'tests', 'routes', 'graphql', 'config']

TEXT_EXT = {'.php', '.blade.php', '.graphql', '.proto', '.dart', '.json', '.md'}

# Files where catalog_id is the CORRECT column (the new Package tier link).
KEEP_CATALOG_ID = {
    os.path.join('app', 'Models', 'Package', 'Package.php'),
    os.path.join('app', 'Models', 'Catalog', 'Catalog.php'),
    os.path.join('app', 'Filament', 'Admin', 'Resources', 'PackageResource', 'PackageResource.php'),
    os.path.join('database', 'migrations', '2026_10_13_000001_add_catalog_id_to_packages_table.php'),
}

MODELS_WITH_ALIAS = [
    os.path.join('app', 'Models', 'Cart', 'Cart.php'),
    os.path.join('app', 'Models', 'Order', 'Order.php'),
    os.path.join('app', 'Models', 'Review', 'Review.php'),
    os.path.join('app', 'Models', 'Wishlist', 'Wishlist.php'),
]


def rel(path):
    return os.path.relpath(path, ROOT).replace(os.sep, os.sep)


def revert_fk():
    changed = []
    for top in SCAN_ROOTS:
        base = os.path.join(ROOT, top)
        if not os.path.isdir(base):
            continue
        for dirpath, dirnames, filenames in os.walk(base):
            dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
            for name in filenames:
                if not any(name.endswith(e) for e in TEXT_EXT):
                    continue
                path = os.path.join(dirpath, name)
                r = rel(path)
                if r in KEEP_CATALOG_ID:
                    continue
                try:
                    with io.open(path, 'r', encoding='utf-8') as fh:
                        src = fh.read()
                except (UnicodeDecodeError, OSError):
                    continue
                if 'catalog_id' not in src:
                    continue
                out = src.replace('catalog_id', 'product_id')
                with io.open(path, 'w', encoding='utf-8', newline='') as fh:
                    fh.write(out)
                changed.append(r)
    return changed


DUPLICATE_RE = re.compile(
    r'( *)public function catalog\(\)(: BelongsTo)?\r?\n'
    r' *\{\r?\n'
    r' *return \$this->catalog\(\);\r?\n'
    r' *\}\r?\n'
)


def fix_alias():
    """Turn the duplicated catalog() (formerly product() alias) back into product()."""
    fixed = []
    for r in MODELS_WITH_ALIAS:
        path = os.path.join(ROOT, r)
        with io.open(path, 'r', encoding='utf-8') as fh:
            src = fh.read()

        def repl(m):
            indent, ret = m.group(1), m.group(2) or ''
            return ('{0}public function product(){1}\n'
                    '{0}{{\n'
                    '{0}    return $this->catalog();\n'
                    '{0}}}\n').format(indent, ret)

        out, n = DUPLICATE_RE.subn(repl, src)
        if n:
            # Repair the comment the earlier pass mangled.
            out = out.replace(
                'The relation is now called catalog(); catalog() used to be the canonical',
                'The relation is now called catalog(); product() used to be the canonical')
            out = out.replace(
                'Canonical relation name (the model was renamed Catalog -> Catalog).',
                'Canonical relation name (the model was renamed Product -> Catalog).')
            with io.open(path, 'w', encoding='utf-8', newline='') as fh:
                fh.write(out)
            fixed.append((r, n))
    return fixed


def main():
    changed = revert_fk()
    print('== Reverted catalog_id -> product_id in %d files ==' % len(changed))
    for r in sorted(changed):
        print('  ' + r)

    fixed = fix_alias()
    print('\n== Restored product() alias in %d models ==' % len(fixed))
    for r, n in fixed:
        print('  %s (%d occurrence(s))' % (r, n))

    return 0


if __name__ == '__main__':
    sys.exit(main())
