# -*- coding: utf-8 -*-
"""
Make `catalog` the canonical Eloquent relation name while keeping `product`
working as a thin alias.

Rationale: the model class was renamed Product -> Catalog, but `product()`
relation (and the `$model->product` dynamic property) is referenced from blade
templates, Filament resources, API controllers and tests. Deleting it outright
would break all of them, so catalog() becomes the real relation and product()
simply delegates to it.

Run:  python tools/add_catalog_relations.py
"""

import io
import os
import re
import sys

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

# file -> (existing relation method to wrap, doc comment for the alias)
TARGETS = [
    ('app/Models/Cart/Cart.php', 'product'),
    ('app/Models/Order/Order.php', 'product'),
    ('app/Models/Review/Review.php', 'product'),
    ('app/Models/Wishlist/Wishlist.php', 'product'),
]

ALIAS_BLOCK = """
    /**
     * Alias kept for backward compatibility.
     *
     * The relation is now called catalog(); product() used to be the canonical
     * name and is still used by existing views, resources and API code.
     */
    public function {old}()
    {{
        return $this->catalog();
    }}
"""

NEW_RELATION = """
    public function catalog()
    {{
        return $this->belongsTo(Catalog::class);
    }}
"""


def wrap_cart(path, src):
    """Cart::product() declares an explicit BelongsTo return type."""
    pattern = re.compile(
        r'(    public function product\(\): BelongsTo\r?\n    \{\r?\n'
        r'.*?\r?\n    \}\r?\n)',
        re.DOTALL,
    )
    m = pattern.search(src)
    if not m:
        return src, False
    replacement = (
        NEW_RELATION.lstrip('\n')
        + '\n'
        + ALIAS_BLOCK.format(old='product').lstrip('\n')
        + m.group(1)
    )
    return src[:m.start()] + replacement + src[m.end():], True


def wrap_generic(path, src):
    """Review/Order/Wishlist use the short `belongsTo(X::class);` body."""
    pattern = re.compile(
        r'    public function product\(\)\r?\n'
        r'    \{\r?\n'
        r'(        return \$this->belongsTo\(Catalog::class\);\r?\n)'
        r'    \}\r?\n'
    )
    m = pattern.search(src)
    if not m:
        return src, False
    replacement = (
        NEW_RELATION.lstrip('\n')
        + '\n'
        + ALIAS_BLOCK.format(old='product').lstrip('\n')
    )
    return src[:m.start()] + replacement + src[m.end():], True


def add_category_aliases(path, src):
    if 'public function catalogs()' in src:
        return src, False
    anchor = '    public function categoryProducts()'
    if anchor not in src:
        return src, False
    block = (
        '    public function catalogs()\n'
        '    {\n'
        '        return $this->categoryProducts();\n'
        '    }\n\n'
        '    /**\n'
        '     * Alias kept for backward compatibility with the pre-rename name.\n'
        '     */\n'
    )
    idx = src.index(anchor)
    return src[:idx] + block + src[idx:], True


def main():
    for rel, kind in TARGETS:
        path = os.path.join(ROOT, rel.replace('/', os.sep))
        with io.open(path, 'r', encoding='utf-8') as fh:
            src = fh.read()

        if 'public function catalog()' in src:
            print('skip (already has catalog()): %s' % rel)
            continue

        if kind == 'product':
            new_src, ok = (wrap_cart if rel.endswith('Cart/Cart.php') else wrap_generic)(path, src)
        else:
            new_src, ok = add_category_aliases(path, src)

        if ok:
            with io.open(path, 'w', encoding='utf-8', newline='') as fh:
                fh.write(new_src)
            print('updated: %s' % rel)
        else:
            print('NO MATCH: %s' % rel)


if __name__ == '__main__':
    sys.exit(main())
