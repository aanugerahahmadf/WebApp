<?php

namespace App\Models\UserLanguage;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $model_id
 * @property string $model_type
 * @property string|null $lang
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|\Eloquent $model
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLanguage newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLanguage newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLanguage query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLanguage whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLanguage whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLanguage whereLang($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLanguage whereModelId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLanguage whereModelType($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|UserLanguage whereUpdatedAt($value)
 *
 * @property string $modelId
 * @property string $modelType
 * @property Carbon|null $createdAt
 * @property Carbon|null $updatedAt
 *
 * @mixin \Eloquent
 */
class UserLanguage extends Model
{
    use LegacyMorphClass;
    protected $fillable = [
        'model_id',
        'model_type',
        'lang',
    ];

    protected $dates = [
        'created_at',
        'updated_at',
    ];

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}
