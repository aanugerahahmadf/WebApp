<?php

namespace App\Models\Review;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;


use App\Models\Order\Order;
use App\Models\Package\Package;
use App\Models\Catalog\Catalog;
use App\Models\ReviewReply\ReviewReply;
use App\Models\ReviewVote\ReviewVote;
use App\Models\User\User;
use App\Support\AppPlatform\AppPlatform;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use App\Models\Traits\ResolvesCamelCaseAttributes\ResolvesCamelCaseAttributes;


/**
 * @property int $id
 * @property int $user_id
 * @property int|null $order_id
 * @property int|null $package_id
 * @property int|null $catalog_id
 * @property int $rating
 * @property string|null $title
 * @property string|null $comment
 * @property string|null $variation
 * @property array|null $specifications
 * @property string|null $photo
 * @property array|null $photos
 * @property int $helpful_count
 * @property bool $is_anonymous
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Order|null $order
 * @property-read Package|null $package
 * @property-read Catalog|null $catalog
 * @property-read Collection<int, ReviewReply> $replies
 * @property-read Collection<int, ReviewVote> $votes
 *
 * @mixin \Eloquent
 */
class Review extends Model
{
    use ResolvesCamelCaseAttributes;
    use HasFactory;
    use LegacyMorphClass;
    protected $appends = [
        'photo_url',
        'photo_urls',
        'tier_id',
        'masked_username',
        'seller_reply',
        'is_verified_purchase',
        'reviewer_name',
        'reviewer_avatar',
    ];

    /** Package is the legacy storage name for a catalog tier. */
    public function getTierIdAttribute(): ?int
    {
        return $this->package_id === null ? null : (int) $this->package_id;
    }

    protected $fillable = [
        'user_id',
        'order_id',
        'package_id',
        'catalog_id',
        'rating',
        'title',
        'comment',
        'variation',
        'specifications',
        'photo',
        'photos',
        'helpful_count',
        'is_anonymous',
    ];

    protected $casts = [
        'photos' => 'array',
        'specifications' => 'array',
        'helpful_count' => 'integer',
        'is_anonymous' => 'boolean',
        'order_id' => 'integer',
    ];

    /**
     * Shopee-style username masking: e.g. "b*****8" or "u*****1"
     */
    public function getMaskedUsernameAttribute(): string
    {
        $name = $this->user?->full_name ?? $this->user?->name ?? 'Pengguna';
        $len = mb_strlen($name);
        if ($len <= 2) {
            return $name;
        }
        $first = mb_substr($name, 0, 1);
        $last = mb_substr($name, -1);
        return $first . str_repeat('*', max(3, $len - 2)) . $last;
    }

    /**
     * Shopee-style primary seller reply
     */
    public function getSellerReplyAttribute(): ?string
    {
        return $this->replies->first()?->comment;
    }

    /**
     * Nama yang boleh ditampilkan di daftar ulasan publik.
     *
     * Ulasan anonim memakai nama tersamar; pemilik ulasan tetap melihat
     * namanya sendiri lewat endpoint "Ulasan Saya" (controller menimpanya
     * setelah serialisasi).
     */
    public function getReviewerNameAttribute(): string
    {
        if ($this->is_anonymous) {
            return $this->masked_username;
        }

        return $this->user?->full_name
            ?? $this->user?->name
            ?? __('Pengguna');
    }

    public function getReviewerAvatarAttribute(): ?string
    {
        if ($this->is_anonymous) {
            return null;
        }

        return $this->user?->avatar_url;
    }

    /**
     * Ulasan dianggap pembelian terverifikasi bila terhubung ke order.
     * Tidak perlu kolom duplikat: `order_id` adalah sumber kebenarannya.
     */
    public function getIsVerifiedPurchaseAttribute(): bool
    {
        return $this->order_id !== null;
    }

    public function getItemNameAttribute(): string
    {
        return $this->catalog?->name
            ?? $this->package?->name
            ?? __('Ulasan');
    }

    public function getItemImageUrlAttribute(): ?string
    {
        if ($this->catalog) {
            return $this->catalog->thumbnail_url ?: $this->catalog->image_url;
        }

        return $this->package?->image_url;
    }

    public function getPhotoUrlAttribute(): ?string
    {
        $urls = $this->getPhotoUrlsAttribute();

        return $urls[0] ?? null;
    }

    /**
     * URL foto ulasan, aman untuk path relatif, path `storage/...`, URL
     * absolut, dan data URI.
     */
    public function getPhotoUrlsAttribute(): array
    {
        $paths = $this->photos ?: [];

        if (empty($paths) && $this->photo) {
            $paths = [$this->photo];
        }

        $urls = [];

        foreach ((array) $paths as $path) {
            if (! is_string($path)) {
                continue;
            }

            $path = trim($path);

            if ($path === '') {
                continue;
            }

            if (str_starts_with($path, 'data:')) {
                $urls[] = $path;

                continue;
            }

            if (preg_match('#^https?://#i', $path) === 1) {
                $urls[] = AppPlatform::normalizeUrl($path);

                continue;
            }

            if (str_starts_with($path, '/')) {
                $urls[] = AppPlatform::normalizeUrl(url($path));

                continue;
            }

            if (str_starts_with($path, 'storage/')) {
                $urls[] = AppPlatform::normalizeUrl(asset($path));

                continue;
            }

            $urls[] = AppPlatform::normalizeUrl(asset('storage/'.ltrim($path, '/')));
        }

        return array_values(array_unique(array_filter($urls)));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function catalog()
    {
        return $this->belongsTo(Catalog::class, 'catalog_id');
    }

    public function replies()
    {
        return $this->hasMany(ReviewReply::class)->orderBy('created_at', 'asc');
    }

    public function votes()
    {
        return $this->hasMany(ReviewVote::class);
    }

    public function reports()
    {
        return $this->hasMany(ReviewReport::class);
    }

    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    public function scopeForCatalog(Builder $query, int $catalogId): Builder
    {
        return $query->where('catalog_id', $catalogId);
    }

    public function scopeForPackage(Builder $query, int $packageId): Builder
    {
        return $query->where('package_id', $packageId);
    }

    public function scopeWithMedia(Builder $query): Builder
    {
        return $query->where(function (Builder $q): void {
            $q->where(function (Builder $photo): void {
                $photo->whereNotNull('photo')->where('photo', '<>', '');
            })->orWhereJsonLength('photos', '>', 0);
        });
    }

    public function scopeWithComment(Builder $query): Builder
    {
        return $query->whereNotNull('comment')->where('comment', '<>', '');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim((string) $term);

        if ($term === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($term): void {
            $like = '%'.$term.'%';

            $q->where('title', 'like', $like)
                ->orWhere('comment', 'like', $like)
                ->orWhereHas('user', function (Builder $user) use ($like): void {
                    $user->where('full_name', 'like', $like)
                        ->orWhere('username', 'like', $like);
                })
                ->orWhereHas('catalog', fn (Builder $catalog) => $catalog->where('name', 'like', $like))
                ->orWhereHas('package', fn (Builder $package) => $package->where('name', 'like', $like));
        });
    }

    public function scopeSorted(Builder $query, ?string $sort): Builder
    {
        return match ($sort) {
            'oldest'       => $query->orderBy('created_at'),
            'highest'      => $query->orderByDesc('rating')->orderByDesc('created_at'),
            'lowest'       => $query->orderBy('rating')->orderByDesc('created_at'),
            'most_helpful' => $query->orderByDesc('helpful_count')->orderByDesc('created_at'),
            default        => $query->orderByDesc('created_at'),
        };
    }

    /**
     * Check if a user has voted this review as helpful.
     */
    public function isVotedBy(?int $userId): bool
    {
        if ($userId === null) {
            return false;
        }

        return $this->votes()->where('user_id', $userId)->exists();
    }
}
