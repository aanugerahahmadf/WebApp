<?php

namespace App\Models\Message;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;


use App\Models\Inbox\Inbox;
use App\Models\User\User;

use App\Enums\Messages\MediaCollectionType\MediaCollectionType;
use App\Models\Traits\HasMediaConvertionRegistrations\HasMediaConvertionRegistrations;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Collections\MediaCollection;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int $inbox_id
 * @property string|null $message
 * @property int $user_id
 * @property array<array-key, mixed>|null $read_by
 * @property array<array-key, mixed>|null $read_at
 * @property array<array-key, mixed>|null $notified
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read MediaCollection<int, Media> $attachments
 * @property-read int|null $attachments_count
 * @property-read Inbox|null $inbox
 * @property-read MediaCollection<int, Media> $media
 * @property-read int|null $media_count
 * @property-read User|null $sender
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereInboxId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereMessage($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereNotified($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereReadAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereReadBy($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Message whereUserId($value)
 * @method static \App\Models\Message\Message|null find(mixed $id, array|string $columns = ['*'])
 * @method static \App\Models\Message\Message findOrFail(mixed $id, array|string $columns = ['*'])
 * @method static \App\Models\Message\Message|null first(array|string $columns = ['*'])
 * @method static \App\Models\Message\Message firstOrFail(array|string $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection<int, \App\Models\Message\Message> get(array|string $columns = ['*'])
 *
 * @property int $inboxId
 * @property int $userId
 * @property array<array-key, mixed>|null $readBy
 * @property array<array-key, mixed>|null $readAt
 * @property Carbon|null $createdAt
 * @property Carbon|null $updatedAt
 * @property Carbon|null $deletedAt
 * @property-read int|null $attachmentsCount
 * @property-read bool|null $attachmentsExists
 * @property-read int|null $mediaCount
 * @property-read bool|null $mediaExists
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Message\Message withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|\App\Models\Message\Message withoutTrashed()
 *
 * @mixin \Eloquent
 */
class Message extends Model implements HasMedia
{
    use LegacyMorphClass;
    use HasMediaConvertionRegistrations, SoftDeletes;

    protected $table = 'fm_messages';

    protected $fillable = [
        'inbox_id',
        'message',
        'user_id',
        'read_by',
        'read_at',
        'notified',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'read_by' => 'array',
            'read_at' => 'array',
            'notified' => 'array',
            'meta' => 'array',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(MediaCollectionType::FILAMENT_MESSAGES->value)
            ->registerMediaConversions($this->modelMediaConvertionRegistrations());
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Media::class, 'model')
            ->where('collection_name', MediaCollectionType::FILAMENT_MESSAGES);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function inbox(): BelongsTo
    {
        return $this->belongsTo(Inbox::class);
    }
}
