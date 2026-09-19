<?php

namespace App\Models\ReviewReply;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;


use App\Models\Review\Review;
use App\Models\User\User;

use Illuminate\Database\Eloquent\Model;

class ReviewReply extends Model
{
    use LegacyMorphClass;
    protected $fillable = [
        'review_id',
        'user_id',
        'comment',
    ];

    public function review()
    {
        return $this->belongsTo(Review::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
