<?php

namespace App\Support\MediaLibrary;

use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

/**
 * Simpan semua media satu jenis dalam satu folder datar.
 *
 * Default Spatie (DefaultPathGenerator) memakai id baris media sebagai nama
 * folder, jadi tiap file berakhir di foldernya sendiri:
 *
 *     storage/app/public/721/package-31.png
 *
 * Untuk katalog produk dan paket yang berisi ratusan gambar, itu berarti
 * ratusan folder yang isinya cuma satu file. Boros entry direktori, lambat
 * disisir, dan bikin `storage` terasa cepat penuh padahal isinya kecil.
 *
 * Layout di sini: satu folder per jenis media, semua file di dalamnya:
 *
 *     storage/app/public/Products/1-product-1.png
 *     storage/app/public/Products/48-product-1.png
 *     storage/app/public/Packages/1-package-1.png
 *
 * Catatan kontrak Spatie: `getPath()` harus mengembalikan FOLDER dengan garis
 * miring di akhir, lalu library menempelkan kolom `file_name` apa adanya.
 * Karena itu kelas ini hanya boleh memilih folder -- tidak bisa menyisipkan
 * prefix ke nama file, karena prefix di `getPath()` akan menjadi SUBFOLDER,
 * yaitu persis layout per-folder yang justru ingin dihilangkan.
 *
 * Keunikan nama file dijaga di kolom `media.file_name` (diprefix id model
 * saat data digenerate), bukan di sini.
 */
class CollectionPathGenerator implements PathGenerator
{
    /**
     * Nama folder di disk untuk tiap koleksi.
     *
     * Koleksi yang tidak terdaftar apa adanya memakai nama koleksinya, jadi
     * koleksi baru (mis. `videos`) tetap dapat foldernya sendiri tanpa perlu
     * diubah di sini.
     *
     * @var array<string, string>
     */
    protected const FOLDER_BY_COLLECTION = [
        'product_image' => 'Products',
        'package_image' => 'Packages',
    ];

    public function getPath(Media $media): string
    {
        return $this->getBasePath($media).'/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getBasePath($media).'/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getBasePath($media).'/responsive-images/';
    }

    /**
     * Folder dasar: [prefix/]<folder-koleksi>
     */
    protected function getBasePath(Media $media): string
    {
        $segments = [];

        $prefix = config('media-library.prefix', '');
        if (is_string($prefix) && $prefix !== '') {
            $segments[] = $this->sanitize($prefix);
        }

        $segments[] = $this->sanitize(
            static::FOLDER_BY_COLLECTION[$media->collection_name] ?? $media->collection_name
        );

        return implode('/', $segments);
    }

    /**
     * Cegah path keluar dari folder disk: `collection_name` datang dari DB dan
     * `prefix` dari .env, jadi keduanya belum tentu aman dipakai apa adanya.
     */
    protected function sanitize(string $value): string
    {
        $clean = preg_replace('/[^A-Za-z0-9._-]+/', '-', $value) ?? '';

        return trim($clean, '.') ?: 'media';
    }
}
