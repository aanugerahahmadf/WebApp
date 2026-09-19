<?php

namespace App\Models\Inbox;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;


use App\Models\Message\Message;
use App\Models\User\User;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * @property int $id
 * @property string|null $title
 * @property array<array-key, mixed> $user_ids
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property-read mixed $inbox_title
 * @property-read Collection<int, Message> $messages
 * @property-read int|null $messages_count
 * @property-read mixed $other_users
 *
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inbox newModelQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inbox newQuery()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inbox onlyTrashed()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inbox query()
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inbox whereCreatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inbox whereDeletedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inbox whereId($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inbox whereTitle($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inbox whereUpdatedAt($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inbox whereUserIds($value)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inbox withTrashed(bool $withTrashed = true)
 * @method static \Illuminate\Database\Eloquent\Builder<static>|Inbox withoutTrashed()
 * @method static \App\Models\Inbox\Inbox|null find(mixed $id, array|string $columns = ['*'])
 * @method static \Illuminate\Database\Eloquent\Collection<int, \App\Models\Inbox\Inbox> get(array|string $columns = ['*'])
 *
 * @property array<array-key, mixed> $userIds
 * @property Carbon|null $createdAt
 * @property Carbon|null $updatedAt
 * @property Carbon|null $deletedAt
 * @property-read mixed $inboxTitle
 * @property-read int|null $messagesCount
 * @property-read bool|null $messagesExists
 * @property-read mixed $otherUsers
 *
 * @mixin \Eloquent
 */
class Inbox extends Model
{
    use LegacyMorphClass;
    use SoftDeletes;

    protected $table = 'fm_inboxes';

    protected $fillable = [
        'title',
        'user_ids',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'user_ids' => 'array',
            'meta' => 'array',
        ];
    }

    protected function inboxTitle(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->title) {
                    return $this->title;
                }

                // Support both web (Auth::id) dan mobile (sanctum)
                $authId = Auth::id() ?? auth('sanctum')->id();
                if (! $authId) {
                    return 'Unknown';
                }

                $userIds = collect($this->user_ids);
                $otherParticipants = $userIds->filter(fn ($id) => $id != $authId);

                if ($otherParticipants->isEmpty()) {
                    return Auth::user()?->full_name ?? auth('sanctum')->user()?->full_name ?? 'Diri Sendiri';
                }

                return $otherParticipants->map(function ($userId) {
                    return User::query()->find($userId, ['*'])?->full_name;
                })->values()->filter()->implode(', ') ?: 'Unknown';
            }
        );
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function latestMessage(): ?Message
    {
        return $this->messages()->latest()->first(['*']);
    }

    public function otherUsers(): Attribute
    {
        return Attribute::make(
            get: function () {
                $authId = Auth::id() ?? auth('sanctum')->id();
                if (! $authId || empty($this->user_ids)) {
                    return collect();
                }

                return User::whereIn('id', $this->user_ids)
                    ->where('id', '!=', $authId)
                    ->get(['*']);
            }
        );
    }

    public function primaryAvatar(): Attribute
    {
        return Attribute::make(
            get: function () {
                $otherUser = $this->other_users->first();
                if ($otherUser) {
                    return $otherUser->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($this->inbox_title);
                }

                // If no other user (chat with self), use current user's avatar
                return Auth::user()?->avatar_url ?? 'https://ui-avatars.com/api/?name='.urlencode($this->inbox_title);
            }
        );
    }
}
