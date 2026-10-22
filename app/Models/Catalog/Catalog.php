<?php

namespace App\Models\Catalog;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;


use App\Models\Category\Category;
use App\Models\Discount\Discount;
use App\Models\Order\Order;
use App\Models\Package\Package;
use App\Models\Review\Review;
use App\Models\Wishlist\Wishlist;

use App\Support\AppPlatform\AppPlatform;
use App\Traits\HasTranslations\HasTranslations;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\Image\Enums\Fit;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class Catalog extends Model implements HasMedia
{
    use LegacyMorphClass;
    use HasFactory, HasTranslations, InteractsWithMedia;

    protected $table = 'catalogs';

    /**
     * Nama konversi yang dipakai accessor di bawah.
     *
     * Dipakai lewat konstanta, bukan string mentah, karena nama yang sama juga
     * dipakai `registerMediaConversions()`. Kalau satu nama berubah dan yang lain
     * tidak, hasilnya bukan error yang kelihatan -- `hasGeneratedConversion()`
     * hanya diam-diam mengembalikan false dan semua katalog diam-diam jatuh
     * balik ke gambar ukuran penuh, persis bug yang diperbaiki di sini.
     */
    public const CONVERSION_THUMB = 'thumb';

    public const CONVERSION_MEDIUM = 'medium';

    public const CONVERSION_LARGE = 'large';

    protected array $translatable = ['name', 'description'];

    public function registerMediaCollections(): void
    {
        // Foto katalog. `useResponsiveImages()` sengaja TIDAK dipakai: responsive
        // images menambah beberapa file WebP per foto, dan untuk grid katalog
        // cukup satu thumbnail + satu ukuran penuh. Foto asli (yang dipakai
        // lightbox dan carousel) tetap tersedia sebagai file tanpa konversi.
        $this->addMediaCollection('catalog_image')
            ->acceptsMimeTypes(['image/jpeg', 'image/pjpeg', 'image/png', 'image/webp']);

        $this->addMediaCollection('videos');
    }

    /**
     * Konversi untuk foto katalog.
     *
     * DULU method ini kosong ("conversions disabled to avoid Windows temp file
     * permission issues"), sehingga `thumbnail_url` hanya mengembalikan salinan
     * URL gambar asli. Efeknya grid katalog mengunduh file 200-500 KB untuk
     * kartu setinggi ~300 px: lambat, boros bandwidth, dan sama sekali bukan
     * thumbnail.
     *
     * Sekarang tiga ukuran dihasilkan:
     *
     *   thumb  400x400   kartu katalog di grid (mobile 2 kolom ~180 px, desktop
     *                     3-4 kolom ~300 px, jadi 400 px menutup semua itu
     *                     termasuk DPR 2 pada layar tajam)
     *   medium 900x900   kartu besar / pratinjau sedang, dan sumber untuk
     *                     carousel di halaman detail
     *   large  1600x1600 tampilan penuh saat zoom di halaman detail
     *
     * `fit(Fit::Crop, ...)`-+-bukan `Fit::Max` supaya semua foto satu ukuran
     * rapi dan tidak ada letterbox.
     *
     * `nonQueued()` wajib: konversi langsung jalan saat `addMedia()` dipanggil.
     * Kalau diantrikan, file konversi tidak ada sampai worker queue berjalan,
     * dan `hasGeneratedConversion()` akan false untuk seluruh katalog yang
     * baru di-seed -- itu persis kondisi thumbnail yang hilang.
     *
     * Catatan Windows: Spatie menulis file sementara ke `storage/framework/temp`.
     * Seeder sudah mengarahkan `sys_temp_dir` ke folder temp sistem. Kalau
     * konversi gagal di mesin lain, cek permission folder itu lebih dulu --
     * bukan alasan untuk mematikan konversi.
     */
    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion(self::CONVERSION_THUMB)->fit(Fit::Crop, 400, 400)->nonQueued();
        $this->addMediaConversion(self::CONVERSION_MEDIUM)->fit(Fit::Crop, 900, 900)->nonQueued();
        $this->addMediaConversion(self::CONVERSION_LARGE)->fit(Fit::Crop, 1600, 1600)->nonQueued();
    }

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
        'name_translations',
        'description_translations',
        'price',
        'discount_price',
        'stock',
        'is_active',
        'is_featured',
        'features',
        'theme',
        'color',
        'min_capacity',
        'max_capacity',
    ];

    protected $casts = [
        'features' => 'array',
        'name_translations' => 'json',
        'description_translations' => 'json',
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'stock' => 'integer',
    ];

    protected $appends = [
        'image_url',
        'thumbnail_url',
        'gallery_urls',
        'final_price',
        'is_wishlisted',
        'video_url',
        'average_rating',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($catalog) {
            if (empty($catalog->slug)) {
                $catalog->slug = Str::slug($catalog->name).'-'.Str::random(5);
            }
        });
    }

    public function getNameAttribute($value): ?string
    {
        return $this->trans('name') ?? $value;
    }

    public function getDescriptionAttribute($value): ?string
    {
        return $this->trans('description') ?? $value;
    }

    /**
     * Cover katalog dalam ukuran PENUH (1600px) -- untuk lightbox, carousel, dan
     * tampilan detail.
     *
     * DULU accessor ini mengembalikan file asli apa adanya karena konversi
     * dimatikan. Sekarang memakai konversi `large`, dan tetap jatuh ke file
     * asli kalau konversinya belum tergenerate, supaya katalog lama tidak
     * ikut jadi rusak.
     */
    public function getImageUrlAttribute(): string
    {
        return $this->responsiveUrlFor(
            $this->getFirstMedia('catalog_image'),
            self::CONVERSION_LARGE,
        );
    }

    /**
     * Thumbnail untuk kartu di grid/list.
     *
     * DULU `return $this->image_url;` -- alias ke gambar ukuran penuh. Grid
     * katalog memuat puluhan kartu sekaligus, jadi tiap kartu menarik file
     * 200-500 KB untuk area sekitar 300x300 px.
     *
     * Sekarang memakai konversi `thumb` (400x400, biasanya 20-60 KB). Kalau
     * konversi belum ada, turun ke `medium`, lalu ke file asli -- supaya
     * tidak pernah mengembalikan URL yang 404.
     */
    public function getThumbnailUrlAttribute(): string
    {
        $media = $this->getFirstMedia('catalog_image');

        if (! $media) {
            return $this->placeholderImageUrl();
        }

        return $this->responsiveUrlFor(
            $media,
            self::CONVERSION_THUMB,
            $this->responsiveUrlFor($media, self::CONVERSION_MEDIUM),
        );
    }

    /**
     * Every photo of this catalog - 5 images from media library (catalog_image collection)
     * Returns array of image objects with id, url, thumb, collection, order
     * Always queries media directly to ensure gallery works without eager-loading.
     * Maintains sliding window order: Catalog 1 → images 1-5, Catalog 2 → 2-6, etc.
     *
     * @return array<int, array{id:int,url:string,thumb:string,collection:string,order:int}>
     */
    public function getGalleryUrlsAttribute(): array
    {
        $mediaItems = $this->getMedia('catalog_image')
            ->sortBy(fn ($media): int => (int) ($media->order_column ?? 999999))
            ->values();

        if ($mediaItems->isNotEmpty()) {
            $largeFallback = $this->image_url;

            return $mediaItems->map(fn ($media): array => [
                'id' => (int) $media->getKey(),
                // `url` = ukuran penuh untuk lightbox/carousel.
                // `thumb` = thumbnail untuk strip di bawahnya.
                //
                // DULU keduanya `normalizeImageUrl($media->getUrl())` -- file
                // identik, 200-500 KB, dipanggil dengan SATU argumen padahal
                // metodenya mewajibkan dua. Argumen yang hilang itu yang
                // menggagalkan halaman detail katalog dengan HTTP 500.
                'url' => $this->responsiveUrlFor($media, self::CONVERSION_LARGE, $largeFallback),
                'thumb' => $this->responsiveUrlFor(
                    $media,
                    self::CONVERSION_THUMB,
                    $this->responsiveUrlFor($media, self::CONVERSION_MEDIUM, $largeFallback),
                ),
                'collection' => (string) $media->collection_name,
                'order' => (int) ($media->order_column ?? 0),
            ])->all();
        }

        // Fallback to single image if no media
        return [[
            'id' => 0,
            'url' => $this->image_url,
            'thumb' => $this->image_url,
            'collection' => 'catalog_image',
            'order' => 0,
        ]];
    }

    /**
     * URL hasil konversi untuk media tertentu, dengan fallback ke file asli.
     *
     * Pemanggilan dari blade sering hanya punya satu argumen (tanpa fallback),
     * jadi parameter kedua punya nilai bawaan. Tanpa itu,
     * `getGalleryUrlsAttribute()` memanggil fungsi ini dengan satu argumen dan
     * halaman detail katalog mati dengan HTTP 500
     * ("Too few arguments to function normalizeImageUrl(), 1 passed").
     *
     * `hasGeneratedConversion()` Dicek karena baris media bisa ada tanpa file
     * konversi --mis. baris lama yang di-seed sebelum konversi diaktifkan, atau
     * hasil `clearMediaCollection()` yang tidak ikut membersihkan-- sehingga
     * `getUrl($conversion)` akan mengembalikan URL yang 404.
     */
    private function responsiveUrlFor(mixed $media, ?string $conversion = null, ?string $fallback = null): string
    {
        if (! $media) {
            return $this->normalizeImageUrl(null, $fallback ?? $this->placeholderImageUrl());
        }

        $url = $media->getUrl();

        if (filled($conversion) && method_exists($media, 'hasGeneratedConversion') && $media->hasGeneratedConversion($conversion)) {
            $url = $media->getUrl($conversion);
        }

        return $this->normalizeImageUrl($url, $fallback ?? $this->placeholderImageUrl());
    }

    /**
     * Placeholder yang dipakai saat katalog belum punya foto sama sekali.
     *
     * Dipisah dari `normalizeImageUrl()` supaya pemanggil yang tidak punya
     * fallback tidak perlu mengulang URL placeholder yang sama.
     */
    private function placeholderImageUrl(): string
    {
        return AppPlatform::normalizeUrl(asset('images/placeholders/image-placeholder.png'));
    }

    public function getVideoUrlAttribute(): ?string
    {
        $url = $this->getFirstMediaUrl('videos') ?: null;

        return $url ? AppPlatform::normalizeUrl($url) : null;
    }

    public function getIsOutOfStockAttribute(): bool
    {
        return $this->stock <= 0;
    }

    public function getIsWishlistedAttribute(): bool
    {
        try {
            if (auth('sanctum')->check()) {
                return $this->wishlists()->where('user_id', auth('sanctum')->id())->exists();
            }
        } catch (\Throwable $e) {
        }

        try {
            if (class_exists(Filament::class) && Filament::auth()->check()) {
                return $this->wishlists()->where('user_id', Filament::auth()->id())->exists();
            }
        } catch (\Throwable $e) {
        }

        return false;
    }

    // Foreign keys are pinned explicitly: the related tables were renamed to
    // `catalog_id` alongside this model, but Eloquent would otherwise infer a
    // name from the class basename. The only genuine `catalog_id` in the schema
    // is `packages.catalog_id`, which points at this table and is used by the
    // tiers() relation above.
    public function category()
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    /**
     * Package tiers offered for this catalog (Standar / Sedang / Pelaminan /
     * Tunangan / Lengkap). They are not browsable on their own — they are
     * rendered inside the catalog detail page.
     */
    public function tiers()
    {
        return $this->hasMany(Package::class, 'catalog_id')
            ->orderByRaw('sort_order IS NULL')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function orders()
    {
        return $this->hasMany(Order::class, 'catalog_id');
    }

    public function reviews()
    {
        return $this->hasMany(Review::class, 'catalog_id');
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class, 'catalog_id');
    }

    public function discounts()
    {
        return $this->morphMany(Discount::class, 'discountable');
    }

    public function getCategoryColorAttribute(): string
    {
        return $this->color ?? $this->category?->color ?? '#6366f1';
    }

    public function getAverageRatingAttribute(): float
    {
        return (float) number_format($this->reviews()->avg('rating') ?: 0, 1);
    }

    public function getFinalPriceAttribute(): float
    {
        return ($this->discount_price > 0) ? (float) $this->discount_price : (float) $this->price;
    }

    public function getBadgeStyleAttribute(): string
    {
        $color = $this->category_color;

        return "background: linear-gradient(135deg, {$color} 0%, {$color}cc 100%);
                color: white;
                box-shadow: 0 4px 12px {$color}40;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                padding: 4px 12px;
                border-radius: 99px;
                font-size: 0.7rem;
                border: none;";
    }

    /**
     * Ubah URL media mentah jadi URL absolut yang siap dipakai di blade/API.
     *
     * `$fallback`_opsional_: kalau null, dipakaikan placeholder. Parameter ini
     * wajib punya nilai bawaan karena dipanggil dari beberapa accessor tanpa
     * argumen kedua.
     */
    private function normalizeImageUrl(?string $url, ?string $fallback = null): string
    {
        $fallback ??= $this->placeholderImageUrl();

        if (! filled($url)) {
            return $fallback;
        }

        if (Str::startsWith($url, ['http://', 'https://', 'data:image'])) {
            return AppPlatform::normalizeUrl($url);
        }

        if (Str::startsWith($url, '/')) {
            return AppPlatform::normalizeUrl(url($url));
        }

        $resolved = asset('media/'.ltrim($url, '/'));

        return AppPlatform::normalizeUrl($resolved);
    }
}
