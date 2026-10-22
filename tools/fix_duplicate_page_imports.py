# -*- coding: utf-8 -*-
"""
After renaming Product -> Catalog, the Filament page classes of the Package
resource and the Catalog resource collapsed onto the SAME short names
(ViewCatalog, ManageCatalogs, CheckoutCatalog). Any test importing both now
fails to compile with "Cannot use ... because the name is already in use".

Fix: alias the PackageResource import in every file that also imports the
CatalogResource one. Package pages are slated for deletion anyway (packages
become tiers of a catalog), so aliasing keeps the suite compiling meanwhile.

Run:  python tools/fix_duplicate_page_imports.py
"""

import io
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

TEST_DIRS = [os.path.join(ROOT, 'tests'), os.path.join(ROOT, 'app')]

# short class name -> the aliased name used for the PackageResource import
ALIASES = {
    'ViewCatalog': 'PackageViewCatalog',
    'ManageCatalogs': 'PackageManageCatalogs',
    'CheckoutCatalog': 'PackageCheckoutCatalog',
    'CreateCatalog': 'PackageCreateCatalog',
    'EditCatalog': 'PackageEditCatalog',
    'ListCatalogs': 'PackageListCatalogs',
}

IMPORT_RE = re.compile(
    r'^use (?P<ns>App\\Filament\\(?:User|Welcome|Admin)\\Resources\\'
    r'(?:PackageResource|CatalogResource)\\Pages\\(?P<page>\w+)\\)(?P<cls>\w+);$',
    re.MULTILINE)


def main():
    changed = []
    for base in TEST_DIRS:
        if not os.path.isdir(base):
            continue
        for dirpath, _dirnames, filenames in os.walk(base):
            for name in filenames:
                if not name.endswith('.php'):
                    continue
                path = os.path.join(dirpath, name)
                with io.open(path, 'r', encoding='utf-8') as fh:
                    src = fh.read()

                if 'CatalogResource\\Pages\\' not in src:
                    continue

                imports = list(IMPORT_RE.finditer(src))
                has_pkg = any('PackageResource' in m.group('ns') for m in imports)
                has_cat = any('CatalogResource' in m.group('ns') for m in imports)
                if not (has_pkg and has_cat):
                    continue

                # Collisions are per (short class name) across the two namespaces.
                out = src
                for m in imports:
                    if 'PackageResource' not in m.group('ns'):
                        continue
                    short = m.group('cls')
                    alias = ALIASES.get(short)
                    if not alias:
                        continue
                    # Only alias if the same short name is also imported from CatalogResource.
                    cat_same = any(
                        'CatalogResource' in o.group('ns') and o.group('cls') == short
                        for o in imports
                    )
                    if not cat_same:
                        continue
                    new_line = 'use {0}\\{1} as {2};'.format(m.group('ns'), short, alias)
                    out = out.replace(m.group(0), new_line)

                if out != src:
                    with io.open(path, 'w', encoding='utf-8', newline='') as fh:
                        fh.write(out)
                    changed.append(os.path.relpath(path, ROOT))

    print('Aliased PackageResource page imports in %d files:' % len(changed))
    for rel in sorted(changed):
        print('  ' + rel.replace(os.sep, '/'))
    return 0


if __name__ == '__main__':
    sys.exit(main())
