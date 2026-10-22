# -*- coding: utf-8 -*-
"""
Rename every remaining Product FILE / FOLDER / SYMBOL to Catalog across the
three services.

Phase 1 (done earlier by rename_product_to_catalog.py / rename_product_types.py):
  the Eloquent model and all `Product::` / `Product` type references.

Phase 2 (this script): the filesystem + symbols that still carry the old name:
  Filament resources & pages, API controllers, route files, blade views,
  seeders, GraphQL documents, .proto contracts, Dart gRPC clients.

Deliberately NOT renamed:
  * database/migrations/*create_products_table*      -> the physical table is
    still `products` (no data migration; the model pins $table explicitly).
  * Morph alias string 'App\\Models\\Product'        -> legacy rows in
    media.model_type / model_has_roles depend on it.
  * routes/api/**/ProductController.php              -> these are *route* files
    that are loaded by path; renaming them would change route file names.
    Only the PHP class/namespace inside is renamed.

Ordering matters: deepest paths first so parent renames cannot clobber them.

Run:  python tools/rename_product_files_to_catalog.py
"""

import io
import os
import re
import shutil
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

SKIP_DIRS = {'vendor', 'node_modules', '.git', 'storage', 'bootstrap', 'public',
             '.idea', '.vscode', 'exportedData', 'database.sqlite'}

SCAN_ROOTS = ['app', 'resources', 'database', 'tests', 'routes', 'proto',
              'graphql', 'config', 'lang']

TEXT_EXT = {'.php', '.blade.php', '.graphql', '.proto', '.dart', '.json',
            '.md', '.yaml', '.yml', '.js', '.ts'}

# Protected literals that must survive verbatim.
PROTECT = [
    'App\\Models\\Product',            # legacy morph alias (no trailing \Product)
    'App\\\\Models\\\\Product',         # same, escaped inside .proto strings
    'products',                        # physical table name
    'Products\\\\',                     # proto message refs resolved at runtime
]

# Ordered: longest / most specific first.
SYMBOL_RULES = [
    # --- Filament resource pages (multi-word first) ---
    (r'\bManageProducts\b', 'ManageCatalogs'),
    (r'\bListProducts\b', 'ListCatalogs'),
    (r'\bViewProduct\b', 'ViewCatalog'),
    (r'\bCheckoutProduct\b', 'CheckoutCatalog'),
    (r'\bCreateProduct\b', 'CreateCatalog'),
    (r'\bEditProduct\b', 'EditCatalog'),
    (r'\bCreateReviews\b', 'CreateReviews'),  # unchanged
    # --- exporters / controllers / resources ---
    (r'\bProductExporter\b', 'CatalogExporter'),
    (r'\bProductController\b', 'CatalogController'),
    (r'\bProductResource\b', 'CatalogResource'),
    # --- graphql / proto / dart ---
    (r'\bproductReviews\b', 'catalogReviews'),
    (r'\bproductsByCategory\b', 'catalogsByCategory'),
    (r'\brelatedProducts\b', 'relatedCatalogs'),
    (r'\bsearchProducts\b', 'searchCatalogs'),
    (r'\bfeaturedProducts\b', 'featuredCatalogs'),
    (r'\bonSaleProducts\b', 'onSaleCatalogs'),
    (r'\bproductStats\b', 'catalogStats'),
    (r'\bproducts\b', 'catalogs'),
    (r'\bProducts\b', 'Catalogs'),
    (r'\bproductId\b', 'catalogId'),
    (r'\bproduct_id\b', 'catalog_id'),
    (r'\bproductIds\b', 'catalogIds'),
    (r'\bproductSlug\b', 'catalogSlug'),
    (r'\bProduct\b', 'Catalog'),
    (r'\bproduct\b', 'catalog'),
]

COMPILED = [(re.compile(p), r) for p, r in SYMBOL_RULES]


def protect(text):
    """Replace protected literals with sentinels so rules can't corrupt them."""
    store = []
    for lit in PROTECT:
        if lit in text:
            token = '\x00P%d\x00' % len(store)
            store.append(lit)
            text = text.replace(lit, token)
    return text, store


def restore(text, store):
    for i, lit in enumerate(store):
        text = text.replace('\x00P%d\x00' % i, lit)
    return text


def rename_content(text):
    text, store = protect(text)
    for pattern, repl in COMPILED:
        text = pattern.sub(repl, text)
    return restore(text, store)


def path_map():
    """Collect every path whose name contains Product (deepest first)."""
    items = []
    for top in SCAN_ROOTS:
        base = os.path.join(ROOT, top)
        if not os.path.isdir(base):
            continue
        for dirpath, dirnames, filenames in os.walk(base):
            dirnames[:] = [d for d in dirnames if d not in SKIP_DIRS]
            for name in list(dirnames) + filenames:
                if 'Product' in name:
                    full = os.path.join(dirpath, name)
                    depth = full.count(os.sep)
                    items.append((depth, full))
    items.sort(key=lambda t: -t[0])
    return [p for _, p in items]


def is_migration(path):
    return os.sep + 'database' + os.sep + 'migrations' + os.sep in path


def is_route_file(path):
    return os.sep + 'routes' + os.sep in path


def main():
    renamed_paths = []
    skipped = []

    # 1) rename files/folders on disk (deepest first)
    for full in path_map():
        if is_migration(full) or is_route_file(full):
            skipped.append(os.path.relpath(full, ROOT))
            continue
        parent = os.path.dirname(full)
        new_name = rename_content(os.path.basename(full))
        target = os.path.join(parent, new_name)
        if target == full:
            continue
        if os.path.exists(target):
            print('SKIP (target exists): %s' % os.path.relpath(full, ROOT))
            continue
        shutil.move(full, target)
        renamed_paths.append((os.path.relpath(full, ROOT), os.path.relpath(target, ROOT)))

    print('== Renamed paths: %d ==' % len(renamed_paths))
    for old, new in renamed_paths:
        print('  %s  ->  %s' % (old.replace(os.sep, '/'), new.replace(os.sep, '/')))

    # 2) rewrite contents of every text file we are allowed to touch
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
                try:
                    with io.open(path, 'r', encoding='utf-8') as fh:
                        src = fh.read()
                except (UnicodeDecodeError, OSError):
                    continue
                if 'Product' not in src and 'product' not in src:
                    continue
                out = rename_content(src)
                if out != src:
                    with io.open(path, 'w', encoding='utf-8', newline='') as fh:
                        fh.write(out)
                    changed.append(os.path.relpath(path, ROOT))

    print('\n== Rewritten files: %d ==' % len(changed))
    for rel in sorted(changed):
        print('  ' + rel.replace(os.sep, '/'))

    if skipped:
        print('\n== Intentionally left in place (contents still rewritten) ==')
        for rel in skipped:
            print('  ' + rel.replace(os.sep, '/'))

    return 0


if __name__ == '__main__':
    sys.exit(main())
