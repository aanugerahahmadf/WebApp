<?php

namespace App\Models\Category;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;


use App\Models\Package\Package;
use App\Models\Catalog\Catalog;

use App\Traits\HasTranslations\HasTranslations;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use LegacyMorphClass;
    use HasFactory;
    use HasTranslations;

    /**
     * Tipe yang dipakai saat kategori dibuat tanpa tipe eksplisit.
     *
     * Kolom `type` tidak lagi diisi dari form -- panel admin cuma menanyakan
     * NAMA kategori. Semua 10 kategori yang ada di DB sudah `catalog`, dan
     * `packages.category_id` menunjuk kategori yang sama persis dengan
     * `catalogs.category_id`, jadi pemisahan paket/katalog di data sudah tidak
     * berarti.
     *
     * Nilai default ini bukan sekadar Kenyamanan: `categories.type` NOT NULL,
     * jadi tanpa pengisian otomatis di `booted()` di bawah, INSERT tanpa tipe
     * akan ditolak database dengan "Field 'type' doesn't have a default value".
     */
    public const DEFAULT_TYPE = 'catalog';

    protected array $translatable = ['name', 'description'];

    protected $fillable = ['name', 'slug', 'type', 'icon', 'color', 'description', 'name_translations', 'description_translations'];

    protected $casts = [
        'name_translations' => 'json',
        'description_translations' => 'json',
    ];

    /**
     * Slug dan tipe diisi otomatis, dari nama -- bukan dari form.
     *
     * Dipindah ke model (bukan ke action Filament) supaya berlaku di semua
     * jalur: panel admin, GraphQL, gRPC, seeder, dan script Artisan.
     *
     * `slug` dan `type` keduanya NOT NULL, jadi keduanya harus selalu terisi,
     * dan slug yang diisi manual bisa bentrok -- di sini selalu unik.
     */
    protected static function booted(): void
    {
        static::saving(static function (self $category): void {
            /*
             * Slug hanya ditulis ulang saat kolom `name` benar-benar berubah,
             * atau saat slug-nya kosong. Kalau selalu ditulis ulang, accessor
             * `getNameAttribute()` -- yang mengembalikan `name_translations`
             * sesuai bahasa aktif -- akan mengubah slug diam-diam begitu
             * kategori disimpan dalam bahasa Inggris: `Buket Bunga` (slug
             * `buket-bunga`) akan berubah jadi `flower-bouquet` hanya karena
             * panel sedang berbahasa Inggris. URL publik pun ikut berubah.
             *
             * Yang dibandingkan adalah kolom RAW, bukan accessor, karena yang
             * diedit admin dari form adalah kolom itu.
             */
            $rawName = (string) ($category->getAttributes()['name'] ?? '');

            // Kolom `name` kosong tapi yang terisi hanya terjemahan (kategori
            // dari sync terjemahan): pakai nama yang tampil supaya slug-nya
            // tetap berguna.
            $name = $rawName !== ''
                ? $rawName
                : (string) $category->getAttribute('name');

            $nameChanged = ! $category->exists
                || $rawName !== (string) ($category->getRawOriginal('name') ?? '');

            if (blank($category->getAttribute('slug')) || $nameChanged) {
                $category->setAttribute('slug', static::uniqueSlug($name, $category));
            }

            if (blank($category->getAttribute('type'))) {
                $category->setAttribute('type', self::DEFAULT_TYPE);
            }
        });
    }

    /**
     * Slug unik dari sebuah nama: `slug`, lalu `slug-2`, `slug-3`, ...
     *
     * Record yang sedang disimpan diabaikan supaya pengubahan nama tidak
     * bendrok dengan slug miliknya sendiri.
     */
    public static function uniqueSlug(?string $source, ?self $ignore = null): string
    {
        $base = str($source ?? '')->slug()->toString();

        if ($base === '') {
            $base = 'kategori';
        }

        $slug = $base;
        $suffix = 2;

        while (static::query()
            ->where('slug', $slug)
            ->when($ignore?->exists, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists()) {
            $slug = $base.'-'.$suffix;
            $suffix++;
        }

        return $slug;
    }

    public function getNameAttribute($value): ?string
    {
        return $this->trans('name') ?? $value;
    }

    public function getDescriptionAttribute($value): ?string
    {
        return $this->trans('description') ?? $value;
    }

    public function categoryPackages()
    {
        return $this->hasMany(Package::class);
    }

    /**
     * Catalog rows in this category.
     */
    public function catalogs()
    {
        return $this->hasMany(Catalog::class, 'category_id');
    }

    /**
     * Catalog rows in this category.
     */
    public function categoryCatalogs()
    {
        return $this->hasMany(Catalog::class, 'category_id');
    }

    public function scopeForPackages($query)
    {
        return $query->where('type', 'package');
    }

    /**
     * The Catalog discriminator on categories.type.
     */
    public function scopeForCatalogs($query)
    {
        return $query->where('type', 'catalog');
    }

    public function getIsActiveAttribute(): bool
    {
        return true;
    }

    public function packages()
    {
        return $this->hasMany(Package::class, 'category_id');
    }

    public function children()
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    public function parent()
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }
}
