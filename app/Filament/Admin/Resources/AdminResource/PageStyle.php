<?php

namespace App\Filament\Admin\Resources\AdminResource;

/**
 * DEKLARASI TAMPILAN HALAMAN ini -- milik folder resource AdminResource
 * sendiri (satu-satu, bukan template gabungan): struktur teks
 * halaman Admin TIDAK SAMA dengan halaman lain. Ada halaman
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
}
