<?php

namespace App\Filament\Admin\Resources\CatalogResource;

/**
 * DEKLARASI TAMPILAN HALAMAN ini -- milik folder resource CatalogResource
 * sendiri (satu-satu, bukan template gabungan): struktur teks
 * halaman Catalog TIDAK SAMA dengan halaman lain. Ada halaman
 * cukup Head 1 lalu langsung Deskripsi; ada yang berjenjang
 * Head 1, Head 2, Head 3, lalu Deskripsi.
 *
 * Form kustomizer DI BAWAH TABEL halaman ini merender fieldset
 * SESUAI daftar TEXT_ROLES di sini. Perannya jadi penanda
 * [data-typo="<peran>"] di tampilan halaman, dan CSS-nya satu
 * pintu: App\Support\PageStyles\PageStyles::styles().
 *
 * Mengubah struktur ke depan = cukup edit TEXT_ROLES di file ini
 * (peran harus bagian dari whitelist PageStyles::ROLES).
 */
class PageStyle
{
    /** @var list<string> */
    public const TEXT_ROLES = [
        'heading',
        'subheading',
        'description',
    ];

    /** @var array<string, string> */
    public const TEXT_FIELDS = [
        'name' => 'Nama Catalog',
        'category' => 'Kategori',
        'price' => 'Harga',
        'rating' => 'Rating',
        'stock' => 'Stok',
        'description' => 'Deskripsi',

        // Tier/package values rendered by the package table and the User
        // catalog tier selector/detail view.
        'tier_name' => 'Nama Tier',
        'tier_price' => 'Harga Tier',
        'tier_discount_price' => 'Harga Diskon Tier',
        'tier_stock' => 'Stok Tier',
        'tier_description' => 'Deskripsi Tier',
        'tier_features' => 'Fitur Tier',
    ];
}
