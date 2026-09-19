<?php

namespace App\Models\Report;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;


use App\Models\User\User;

use App\Enums\ReportStatus\ReportStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $reportable_type
 * @property int|null $reportable_id
 * @property string $category
 * @property string|null $reason
 * @property string|null $description
 * @property ReportStatus $status
 * @property \Illuminate\Support\Carbon|null $resolved_at
 * @property int|null $resolved_by
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User $user
 * @property-read Model|null $reportable
 */
class Report extends Model
{
    use LegacyMorphClass;
    protected $fillable = [
        'user_id',
        'reportable_type',
        'reportable_id',
        'category',
        'reason',
        'description',
        'status',
        'resolved_at',
        'resolved_by',
    ];

    protected $casts = [
        'status' => ReportStatus::class,
        'resolved_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', ReportStatus::OPEN);
    }
}
