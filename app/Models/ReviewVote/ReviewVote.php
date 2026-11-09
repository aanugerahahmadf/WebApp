<?php

namespace App\Models\ReviewVote;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;


use App\Models\Review\Review;
use App\Models\User\User;

use Illuminate\Database\Eloquent\Model;
use App\Models\Traits\ResolvesCamelCaseAttributes\ResolvesCamelCaseAttributes;


class ReviewVote extends Model
{
    use ResolvesCamelCaseAttributes;
    use LegacyMorphClass;
    protected $fillable = [
        'user_id',
        'review_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function review()
    {
        return $this->belongsTo(Review::class);
    }
}
