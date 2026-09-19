<?php

namespace App\Models\Package;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;


use App\Models\Category\Category;
use App\Models\Discount\Discount;
use App\Models\Order\Order;
use App\Models\Review\Review;
use App\Models\Vendor\Vendor;
use App\Models\Wishlist\Wishlist;

use App\Providers\NativeServiceProvider\NativeServiceProvider;
use App\Traits\HasTranslations\HasTranslations;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * @property int $id
 * @property int|null $category_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property float $price
 * @property float|null $discount_price
 * @property bool $is_featured
 * @property array<array-key, mixed>|null $features
 * @property string|null $theme
 * @property string|null $color
 * @property int|null $min_capacity
 * @property int|null $max_capacity
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Category|null $category
 * @property-read mixed $image_url
 * @property-read bool $is_wishlisted
 * @property-read string|null $video_url
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read Collection<int, Order> $orders
 * @property-read int|null $orders_count
 * @property-read Collection<int, Review> $reviews
 * @property-read int|null $reviews_count
 * @property-read Collection<int, Wishlist> $wishlists
 * @property-read int|null $wishlists_count
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereCategoryId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereColor($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereDescription($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereDiscountPrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereFeatures($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereIsFeatured($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereMaxCapacity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereMinCapacity($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereName($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package wherePrice($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereSlug($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Package whereTheme($value)
 * @method static \App\Models\Package\Package|null find(mixed $id, array|string $columns = ['*'])
 * @method static \App\Models\Package\Package findOrFail(mixed $id, array|string $columns = ['*'])
 * @method static \App\Models\Package\Package|null first(array|string $columns = ['*'])
 * @method static \App\Models\Package\Package firstOrFail(array|string $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection<int, \App\Models\Package\Package> get(array|string $columns = ['*'])
 *
 * @property int|null $categoryId
 * @property numeric|null $discountPrice
 * @property bool $isFeatured
 * @property int|null $minCapacity
 * @property int|null $maxCapacity
 * @property Carbon|null $createdAt
 * @property Carbon|null $updatedAt
 * @property-read mixed $imageUrl
 * @property-read bool $isWishlisted
 * @property-read string|null $videoUrl
 * @property-read int|null $mediaCount
 * @property-read bool|null $mediaExists
 * @property-read int|null $ordersCount
 * @property-read bool|null $ordersExists
 * @property-read int|null $reviewsCount
 * @property-read bool|null $reviewsExists
 * @property-read int|null $wishlistsCount
 * @property-read bool|null $wishlistsExists
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Package\Package whereUpdatedAt($value)
 *
 * @mixin \Eloquent
 */
class Package extends Model implements HasMedia
{
    use LegacyMorphClass;
    use HasFactory, HasTranslations, InteractsWithMedia;

    protected array $translatable = ['name', 'description'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('package_image');
        $this->addMediaCollection('videos');
    }

    protected $fillable = [
        'category_id',
        'name',
        'slug',
        'description',
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
        'name_translations',
        'description_translations',
    ];

    protected $casts = [
        'features' => 'array',
        'price' => 'decimal:2',
        'discount_price' => 'decimal:2',
        'is_active' => 'boolean',
        'is_featured' => 'boolean',
        'stock' => 'integer',
        'name_translations' => 'json',
        'description_translations' => 'json',
    ];

    public function getNameAttribute($value): ?string
    {
        return $this->trans('name') ?? $value;
    }

    public function getDescriptionAttribute($value): ?string
    {
        return $this->trans('description') ?? $value;
    }

    protected $appends = [
        'image_url',
        'final_price',
        'is_wishlisted',
        'video_url',
        'average_rating',
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($package) {
            if (empty($package->slug)) {
                $package->slug = Str::slug($package->name).'-'.Str::random(5);
            }
        });
    }

    public function getImageUrlAttribute()
    {
        $fallback = NativeServiceProvider::normalizeUrl(asset('images/placeholders/image-placeholder.png'));
        $url = $this->getFirstMediaUrl('package_image') ?: null;
        $url = $url ? str_replace('/storage/', '/media/', $url) : $url;

        return $this->normalizeImageUrl($url, $fallback);
    }

    public function getVideoUrlAttribute(): ?string
    {
        $url = $this->getFirstMediaUrl('videos') ?: null;

        return $url ? NativeServiceProvider::normalizeUrl($url) : null;
    }

    public function getIsOutOfStockAttribute(): bool
    {
        return $this->stock <= 0;
    }

    public function getIsWishlistedAttribute(): bool
    {
        // Prioritas: Sanctum (mobile API) dulu, baru Filament (web)
        try {
            if (auth('sanctum')->check()) {
                return $this->wishlists()->where('user_id', auth('sanctum')->id())->exists();
            }
        } catch (\Throwable $e) {
            // Silently fail
        }

        try {
            if (class_exists(Filament::class) && Filament::auth()->check()) {
                return $this->wishlists()->where('user_id', Filament::auth()->id())->exists();
            }
        } catch (\Throwable $e) {
            // Silently fail
        }

        return false;
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function reviews()
    {
        return $this->hasMany(Review::class);
    }

    public function wishlists()
    {
        return $this->hasMany(Wishlist::class);
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

    private function normalizeImageUrl(?string $url, string $fallback): string
    {
        if (! filled($url)) {
            return $fallback;
        }

        // If it's already a full URL or a data URI, return it
        if (Str::startsWith($url, ['http://', 'https://', 'data:image'])) {
            // Normalize host IP for NativePHP mobile
            return NativeServiceProvider::normalizeUrl($url);
        }

        // If it starts with a slash, check if it's already a public path
        if (Str::startsWith($url, '/')) {
            return NativeServiceProvider::normalizeUrl(url($url));
        }

        // Otherwise, resolve via the public storage disk
        $resolved = asset('media/'.ltrim($url, '/'));

        return NativeServiceProvider::normalizeUrl($resolved);
    }
}
