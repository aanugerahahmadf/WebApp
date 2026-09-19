<?php

namespace App\Models\Review;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;


use App\Models\Package\Package;
use App\Models\Product\Product;
use App\Models\ReviewReply\ReviewReply;
use App\Models\ReviewVote\ReviewVote;
use App\Models\User\User;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int|null $package_id
 * @property int|null $product_id
 * @property int $rating
 * @property string|null $title
 * @property string|null $comment
 * @property string|null $photo
 * @property array|null $photos
 * @property int $helpful_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $user
 * @property-read Package|null $package
 * @property-read Product|null $product
 * @property-read Collection<int, ReviewReply> $replies
 * @property-read Collection<int, ReviewVote> $votes
 *
 * @mixin \Eloquent
 */
class Review extends Model
{
    use LegacyMorphClass;
    protected $appends = ['photo_url', 'photo_urls'];

    protected $fillable = [
        'user_id',
        'package_id',
        'product_id',
        'rating',
        'title',
        'comment',
        'photo',
        'photos',
        'helpful_count',
    ];

    protected $casts = [
        'photos' => 'array',
        'helpful_count' => 'integer',
    ];

    public function getPhotoUrlAttribute(): ?string
    {
        $urls = $this->getPhotoUrlsAttribute();

        return $urls[0] ?? null;
    }

    public function getPhotoUrlsAttribute(): array
    {
        $paths = $this->photos ?: [];

        if (empty($paths) && $this->photo) {
            $paths = [$this->photo];
        }

        return array_values(array_filter(array_map(
            fn ($p) => $p ? asset('storage/'.$p) : null,
            (array) $paths
        )));
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function replies()
    {
        return $this->hasMany(ReviewReply::class)->orderBy('created_at', 'asc');
    }

    public function votes()
    {
        return $this->hasMany(ReviewVote::class);
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
