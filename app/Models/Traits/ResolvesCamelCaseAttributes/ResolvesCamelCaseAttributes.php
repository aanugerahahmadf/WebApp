<?php

namespace App\Models\Traits\ResolvesCamelCaseAttributes;

use Illuminate\Support\Str;

/**
 * Fallback pembacaan attribute GraphQL camelCase -> kolom Eloquent snake_case.
 *
 * Lighthouse memetakan field `type Package { sortOrder: Int! }` menjadi
 * `$model->sortOrder`, sedangkan Eloquent menyimpannya sebagai `sort_order`.
 * Tanpa mapping, `getAttribute('sortOrder')` tidak menemukan apa pun, tidak
 * menemukan relasi, lalu mengembalikan null -- cukup untuk membuat
 * "Cannot return null for non-nullable field" pada query yang field-nya
 * non-null (contohnya `Package.sortOrder`, `Catalog.categoryId`).
 *
 * Trait ini hanya menambah satu langkah sebelum perilaku default: bila key
 * camelCase tidak dikenal dan bukan nama metode (relasi/helper), key yang
 * sudah di-snake-kan dicoba dulu. Semua jalur lain -- accessor
 * `getXxxAttribute()`, `Attribute::make()`, cast, relasi, dan exception
 * missing-attribute -- tetap dikerjakan oleh `Model::getAttribute()` apa
 * adanya, jadi model yang sudah punya accessor (mis. `Category`,
 * `Catalog`) tidak berubah perilakunya.
 *
 * Serialisasi tidak terpengaruh: `toArray()`/`toJson()` membaca
 * `$this->attributes` langsung, bukan lewat `getAttribute()`, sehingga bentuk
 * JSON API tetap snake_case seperti sebelumnya.
 */
trait ResolvesCamelCaseAttributes
{
    /**
     * @param  string  $key
     * @return mixed
     */
    public function getAttribute($key)
    {
        // `hasAttribute()` sudah mencakup kolom, cast, dan semua jenis get
        // mutator; `method_exists()` menahan nama relasi/helper supaya tidak
        // pernah ada key yang "ditukar" dengan kolom sebelum relasi dicek.
        if (
            is_string($key)
            && $key !== ''
            && ! $this->hasAttribute($key)
            && ! method_exists($this, $key)
        ) {
            $snake = Str::snake($key);

            if ($snake !== $key && $this->hasAttribute($snake)) {
                return $this->getAttributeValue($snake);
            }
        }

        return parent::getAttribute($key);
    }
}
