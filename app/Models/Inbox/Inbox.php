<?php

namespace App\Models\Inbox;
use App\Models\Traits\LegacyMorphClass\LegacyMorphClass;


use App\Models\Message\Message;
use App\Models\Admin\Admin;
use App\Models\User\User;
use App\Services\GuestIdentity\GuestIdentity;
use App\Support\ChatStaff\ChatStaff;
use App\Support\ChatParticipantName\ChatParticipantName;

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

    /**
     * The account reading this inbox, whoever that happens to be.
     *
     * Three identities, in order, because the project has three ways of knowing
     * who is asking:
     *
     *   - `web` guard  -> panel User and panel Admin, both signed in.
     *   - `sanctum`    -> the mobile app, which sends a bearer token.
     *   - guest cookie -> panel Welcome, where nobody is signed in at all.
     *
     * The third one is why this method exists. The Welcome panel serves guests,
     * and a guest is deliberately NOT logged in (see GuestIdentity): their id
     * lives in the `welcome_guest_id` cookie. Without the fallback below,
     * Auth::id() is null on Welcome, `inbox_title` bailed out with 'Unknown'
     * and `primary_avatar` fell through to ui-avatars.com, so a guest talking
     * to the super admin saw their own thread titled "Unknown" with the
     * initials "UN" -- even though the access check right next to it, which
     * does go through GuestIdentity, happily granted that very same thread.
     *
     * Only the COOKIE is read, never GuestIdentity::id(). That would be one
     * line shorter, but it mints a cookie and INSERTs a users row as a side
     * effect of reading an attribute, so an unauthenticated API call or a
     * console command could silently create accounts. Nothing here writes.
     */
    protected function viewer(): ?User
    {
        $user = Auth::user() ?? auth('sanctum')->user();

        if ($user instanceof User) {
            return $user;
        }

        // Panel admin: `Auth::user()` adalah App\Models\Admin\Admin (guard
        // `admin`, tabel `admins`), sementara `user_ids` menyimpan users.id.
        // Identitas chat admin adalah baris `users` yang tertaut
        // (ChatStaff::adminUser()). Tanpa cabang ini, `inbox_title` jatuh ke
        // "Unknown", `primary_avatar` ke ui-avatars "Unknown", dan
        // `other_users` kosong untuk SEMUA percakapan di panel admin.
        if ($user instanceof Admin) {
            return ChatStaff::adminUser();
        }

        $guestId = request()->cookie(GuestIdentity::COOKIE);

        if (! is_string($guestId) || $guestId === '') {
            return null;
        }

        return User::query()->where('email', GuestIdentity::emailFor($guestId))->first();
    }

    protected function inboxTitle(): Attribute
    {
        return Attribute::make(
            get: function () {
                if ($this->title) {
                    return $this->title;
                }

                $viewerId = $this->viewer()?->getKey();

                if (! $viewerId) {
                    return 'Unknown';
                }

                $userIds = collect($this->user_ids);
                $otherParticipants = $userIds->filter(fn ($id) => (int) $id !== (int) $viewerId);

                if ($otherParticipants->isEmpty()) {
                    // Percakapan dengan diri sendiri: nama orangnya adalah nama
                    // penonton, yang di panel Welcome tetap ada -- hanya lewat
                    // cookie, bukan lewat Auth.
                    return ChatParticipantName::for($this->viewer(), 'Diri Sendiri');
                }

                return $otherParticipants->map(function ($userId) {
                    return ChatParticipantName::for(
                        User::query()->find($userId, ['*']),
                        'Pengguna',
                    );
                })->values()->filter()->implode(', ') ?: 'Pengguna';
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
                $viewerId = $this->viewer()?->getKey();

                if (! $viewerId || empty($this->user_ids)) {
                    return collect();
                }

                return User::whereIn('id', $this->user_ids)
                    ->where('id', '!=', $viewerId)
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
                    return $this->avatarUrl($otherUser->avatar_url);
                }

                // No other participant (chat with self, or a viewer we cannot
                // resolve): fall back to the viewer's own avatar, then to their
                // initials. The viewer, not Auth::user(), because on the Welcome
                // panel the viewer IS a real row and Auth is empty.
                return $this->avatarUrl($this->viewer()?->avatar_url);
            }
        );
    }

    /**
     * URL avatar untuk ditampilkan.
     *
     * `avatar_url` sudah dinormalkan oleh accessor `avatarUrl()` di model
     * User dan Admin: URL yang menunjuk host produksi dipindahkan ke origin
     * request yang sedang berjalan. Jadi di sini tidak perlu normalisasi
     * lagi -- yang tersisa hanya fallback-nya.
     *
     * Tanpa avatar sama sekali, pakai initials dari ui-avatars.com --
     * layanan pihak ketiga juga, jadi tetap perlu CSP yang mengizinkan
     * gambar dari luar (lihat csp.devPolicy di shell Electron).
     */
    protected function avatarUrl(?string $url): string
    {
        if (filled($url)) {
            return $url;
        }

        return 'https://ui-avatars.com/api/?name='.urlencode($this->inbox_title);
    }
}
