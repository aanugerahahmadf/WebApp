<?php

namespace App\Models\Translation;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $source_text
 * @property string $source_hash
 * @property string $target_locale
 * @property string $translated_text
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereSourceHash($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereSourceText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereTargetLocale($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereTranslatedText($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Translation whereUpdatedAt($value)
 *
 * @property string $sourceText
 * @property string $sourceHash
 * @property string $targetLocale
 * @property string $translatedText
 * @property Carbon|null $createdAt
 * @property Carbon|null $updatedAt
 *
 * @mixin \Eloquent
 */
class Translation extends Model
{
    use LegacyMorphClass;
    protected $fillable = [
        'source_text',
        'source_hash',
        'target_locale',
        'translated_text',
    ];
}
