<?php

namespace App\Http\Controllers\Api\User\ChatController;

use App\Enums\Messages\MediaCollectionType\MediaCollectionType;
use App\Http\Controllers\Controller;
use App\Jobs\SendBotReply\SendBotReply;
use App\Models\Inbox\Inbox;
use App\Models\Message\Message;
use App\Models\Order\Order;
use App\Models\User\User;
use App\Services\ChatService\ChatService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class ChatController extends Controller
{
    public function getConversations(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (! $user) {
            return response()->json([
                'success' => false,
                'message' => __('Tidak terautentikasi'),
            ], 401);
        }

        $inboxes = Inbox::whereJsonContains('user_ids', (int) $user->id, 'and', false)
            ->with(['messages' => function ($query): void {
                $query->latest()->limit(1);
            }])
            ->get(['*']);

        $adminUser = User::whereHas('roles', function ($q) {
            $q->where('name', 'super_admin');
        })->first(['*']);
        $adminId = $adminUser?->id ?? 1;
        $isAdmin = $user->hasRole('super_admin');

        $data = $inboxes->map(function (Inbox $inbox) use ($user, $adminId, $isAdmin) {
            $lastMessage = $inbox->messages->first();
            $userIds = $inbox->user_ids ?? [];
            $otherId = collect($userIds)->first(fn ($id) => (int) $id !== (int) $user->id);
            $otherUser = $otherId ? User::find($otherId, ['id', 'full_name', 'username', 'avatar_url']) : null;

            $title = $isAdmin
                ? ($otherUser ? __('Chat dengan :name', ['name' => $otherUser->full_name ?: $otherUser->username ?: __('Pelanggan')]) : $inbox->title ?? __('Chat Bantuan'))
                : __('Chat dengan Admin');

            $unreadCount = Message::where('inbox_id', $inbox->id)
                ->where('user_id', '!=', $user->id)
                ->whereNull('read_by')
                ->orWhere(function ($q) use ($user) {
                    $q->where('user_id', '!=', $user->id)
                        ->whereNotJsonContains('read_by', (string) $user->id);
                })
                ->count();

            return [
                'id' => $inbox->id,
                'title' => $title,
                'other_user' => [
                    'id' => $otherUser?->id ?? $adminId,
                    'name' => $otherUser ? ($otherUser->full_name ?: $otherUser->username ?? __('Pengguna')) : __('Admin'),
                    'profile_photo' => $otherUser?->avatar_url ?? null,
                ],
                'last_message' => $lastMessage ? [
                    'id' => $lastMessage->id,
                    'message' => $lastMessage->message,
                    'created_at' => $lastMessage->created_at->toIso8601String(),
                    'sender_id' => $lastMessage->user_id,
                ] : null,
                'unread_count' => $unreadCount,
                'cs_category' => ($inbox->meta['cs_category'] ?? null),
                'created_at' => $inbox->created_at->toIso8601String(),
                'updated_at' => $inbox->updated_at->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'current_user_is_super_admin' => $isAdmin,
        ]);
    }

    public function getMessages($inboxId)
    {
        $user = Auth::user();
        $inbox = Inbox::findOrFail($inboxId, ['*']);
        $userIds = $inbox->user_ids ?? [];
        if (! in_array((int) $user->id, array_map('intval', $userIds))) {
            return response()->json(['success' => false, 'message' => __('Tidak terautentikasi')], 403);
        }

        $adminUser = User::whereHas('roles', function ($q) {
            $q->where('name', 'super_admin');
        })->first(['id', 'full_name', 'username', 'avatar_url']);
        $adminId = $adminUser?->id ?? 1;
        $otherId = collect($userIds)->first(fn ($id) => (int) $id !== (int) $user->id);
        $otherUser = $otherId ? User::find($otherId, ['id', 'full_name', 'username', 'avatar_url']) : null;

        $messages = Message::where('inbox_id', $inbox->id)
            ->with(['sender:id,full_name,username,avatar_url', 'attachments'])
            ->oldest()
            ->get(['*']);

        $userId = (int) $user->id;

        $data = $messages->map(function (Message $message) use ($userId) {
            $sender = $message->sender;

            return [
                'id' => $message->id,
                'message' => $message->message,
                'sender_id' => $message->user_id,
                'sender_name' => $sender?->full_name ?? $sender?->username ?? __('Tidak dikenal'),
                'is_me' => (int) $message->user_id === $userId,
                'read_by' => $message->read_by ?? [],
                'attachments' => $message->attachments->map(fn (Media $m) => [
                    'id' => $m->id,
                    'url' => str_replace('/storage/', '/media/', $m->getFullUrl()),
                    'original_url' => str_replace('/storage/', '/media/', $m->getFullUrl()),
                    'name' => $m->name,
                    'size' => $m->size,
                    'mime_type' => $m->mime_type,
                ])->values(),
                'meta' => $message->meta,
                'created_at' => $message->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'other_user' => $otherUser ? [
                'id' => $otherUser->id,
                'name' => $otherUser->full_name ?: $otherUser->username ?? __('Admin'),
                'profile_photo' => $otherUser->avatar_url ?? null,
            ] : [
                'id' => $adminId,
                'name' => $adminUser?->full_name ?? __('Admin'),
                'profile_photo' => $adminUser?->avatar_url ?? null,
            ],
        ]);
    }

    public function sendMessage(Request $request)
    {
        $request->validate([
            'inbox_id' => 'required',
            'message' => 'required_without_all:attachment,type,order_id|nullable|string',
            'attachment' => 'nullable|file|mimes:jpg,jpeg,png,gif,webp,mp4,mov,m4v,webm,3gp|max:51200',
            'type' => 'nullable|string',
            'item_id' => 'nullable|integer',
            'item_name' => 'nullable|string',
            'item_price' => 'nullable|numeric',
            'item_image' => 'nullable|string',
            'order_id' => 'nullable|integer',
            'cs_category' => 'nullable|string|max:50',
            'reply_to_id' => 'nullable|integer',
        ]);

        $user = Auth::user();
        $inbox = Inbox::find($request->inbox_id, ['*']);
        if (! $inbox) {
            return response()->json(['success' => false, 'message' => __('Kotak pesan tidak ditemukan')], 404);
        }
        $userIds = $inbox->user_ids ?? [];
        if (! in_array((int) $user->id, array_map('intval', $userIds))) {
            return response()->json(['success' => false, 'message' => __('Tidak terautentikasi')], 403);
        }

        $meta = null;

        if ($request->filled('reply_to_id')) {
            $repliedMessage = Message::find((int) $request->input('reply_to_id'));
            if ($repliedMessage) {
                $repliedSender = $repliedMessage->sender;
                $meta = [
                    'reply_to' => [
                        'id' => $repliedMessage->id,
                        'message' => $repliedMessage->message,
                        'sender_name' => $repliedSender?->full_name ?? $repliedSender?->username ?? __('Tidak dikenal'),
                        'sender_id' => $repliedMessage->user_id,
                    ],
                ];
            }
        } elseif ($request->filled('order_id')) {
            $order = Order::with(['package.media', 'product.media'])->find((int) $request->input('order_id'));
            if ($order) {
                $firstMedia = $order->package?->media?->first() ?? $order->product?->media?->first();
                $meta = [
                    'is_order' => true,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'order_status' => $order->status,
                    'payment_status' => $order->payment_status,
                    'name' => $order->package?->name ?? $order->product?->name ?? '',
                    'image' => $firstMedia ? str_replace('/storage/', '/media/', $firstMedia->getFullUrl()) : '',
                ];
            }
        } elseif ($request->filled('type') && $request->filled('item_id')) {
            $meta = [
                'type' => $request->input('type'),
                'id' => (int) $request->input('item_id'),
                'name' => $request->input('item_name', ''),
                'price' => $request->input('item_price'),
                'image' => $request->input('item_image', ''),
            ];
        }

        $message = Message::create([
            'inbox_id' => $request->inbox_id,
            'user_id' => $user->id,
            'message' => $request->input('message', ''),
            'meta' => $meta,
        ]);

        if ($request->filled('cs_category') && ! $user->hasRole('super_admin')) {
            $inboxMeta = $inbox->meta ?? [];
            if (empty($inboxMeta['cs_category'])) {
                $inboxMeta['cs_category'] = $request->input('cs_category');
                $inbox->meta = $inboxMeta;
                $inbox->save();
            }
        }

        if ($request->hasFile('attachment')) {
            $message->addMedia($request->file('attachment'))
                ->toMediaCollection(MediaCollectionType::FILAMENT_MESSAGES->value);
        }

        if (! $user->hasRole('super_admin')) {
            SendBotReply::dispatch($message->id)->delay(now()->addSeconds(5));
        }

        $message->load('attachments');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $message->id,
                'inbox_id' => $message->inbox_id,
                'user_id' => $message->user_id,
                'message' => $message->message,
                'meta' => $message->meta,
                'created_at' => $message->created_at->toIso8601String(),
                'attachments' => $message->attachments->map(fn (Media $m) => [
                    'id' => $m->id,
                    'url' => str_replace('/storage/', '/media/', $m->getFullUrl()),
                    'original_url' => str_replace('/storage/', '/media/', $m->getFullUrl()),
                    'name' => $m->name,
                    'size' => $m->size,
                    'mime_type' => $m->mime_type,
                ]),
            ],
        ], 201);
    }

    public function startConversation(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $adminUser = User::whereHas('roles', function ($q) {
            $q->where('name', 'super_admin');
        })->first(['id']);
        $adminId = $adminUser ? (int) $adminUser->id : 1;

        $withUserId = $request->input('with_user_id');

        if ($withUserId) {
            $withUserId = (int) $withUserId;
            if ($withUserId === (int) $user->id) {
                return response()->json([
                    'success' => false,
                    'message' => __('Tidak bisa chat dengan diri sendiri.'),
                ], 400);
            }
            $inbox = Inbox::whereJsonContains('user_ids', (int) $user->id, 'and', false)
                ->whereJsonContains('user_ids', $withUserId, 'and', false)
                ->first(['id']);
            if (! $inbox) {
                $inbox = Inbox::create([
                    'user_ids' => [(int) $user->id, $withUserId],
                    'title' => __('Chat Bantuan'),
                ]);
            }
        } else {
            $targetId = $adminId;

            if ((int) $user->id === $targetId) {
                $other = User::whereHas('roles', function ($q) {
                    $q->where('name', 'super_admin');
                })->where('id', '!=', $user->id)->first(['id']);
                if ($other) {
                    $targetId = (int) $other->id;
                } else {
                    $anyUser = User::where('id', '!=', $user->id)->first(['id']);
                    if ($anyUser) {
                        $targetId = (int) $anyUser->id;
                    }
                }
            }

            if ((int) $user->id === $targetId) {
                return response()->json([
                    'success' => false,
                    'message' => __('Tidak ada pengguna lain untuk memulai chat.'),
                ], 400);
            }

            $inbox = Inbox::whereJsonContains('user_ids', (int) $user->id, 'and', false)
                ->whereJsonContains('user_ids', $targetId, 'and', false)
                ->first(['id']);
            if (! $inbox) {
                $inbox = Inbox::create([
                    'user_ids' => [(int) $user->id, $targetId],
                    'title' => __('Chat Bantuan'),
                ]);
            }
        }

        $type = $request->input('type');
        $itemId = $request->input('item_id');
        $orderId = $request->input('order_id');

        if ($orderId) {
            $order = Order::find((int) $orderId);
            if ($order) {
                ChatService::sendOrderMessage($inbox, $order);
            }
        } elseif ($type && $itemId) {
            ChatService::sendContextMessage($inbox, [
                'type' => $type,
                'id' => (int) $itemId,
                'name' => $request->input('item_name', ''),
                'price' => $request->input('item_price'),
                'image' => $request->input('item_image', ''),
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $inbox->id,
            ],
        ], 201);
    }

    public function getCustomersForChat(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        if (! $user->hasRole('super_admin')) {
            return response()->json(['success' => false, 'message' => __('Tidak terautentikasi')], 403);
        }
        $customers = User::where('id', '!=', $user->id)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'username', 'email']);

        return response()->json([
            'success' => true,
            'data' => $customers->map(fn ($u) => [
                'id' => $u->id,
                'full_name' => $u->full_name,
                'username' => $u->username,
                'email' => $u->email,
            ]),
        ]);
    }

    public function getUnreadCount()
    {
        $user = Auth::user();

        $inboxIds = Inbox::whereJsonContains('user_ids', (int) $user->id, 'and', false)
            ->pluck('id');

        $unreadCount = Message::whereIn('inbox_id', $inboxIds)
            ->where('user_id', '!=', $user->id)
            ->where(function ($q) use ($user) {
                $q->whereNull('read_by')
                    ->orWhereNotJsonContains('read_by', (string) $user->id);
            })
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    public function deleteMessage(Request $request, $messageId)
    {
        $user = Auth::user();
        $message = Message::find($messageId);

        if (! $message) {
            return response()->json(['success' => false, 'message' => __('Pesan tidak ditemukan')], 404);
        }

        $inbox = Inbox::find($message->inbox_id, ['*']);
        if (! $inbox) {
            return response()->json(['success' => false, 'message' => __('Kotak pesan tidak ditemukan')], 404);
        }

        $userIds = $inbox->user_ids ?? [];
        if (! in_array((int) $user->id, array_map('intval', $userIds))) {
            return response()->json(['success' => false, 'message' => __('Tidak terautentikasi')], 403);
        }

        $deleteType = $request->input('delete_type', 'me');

        if ($deleteType === 'everyone') {
            if ((int) $message->user_id !== (int) $user->id && ! $user->hasRole('super_admin')) {
                return response()->json(['success' => false, 'message' => __('Tidak diizinkan')], 403);
            }
            $message->delete();
        } else {
            $meta = $message->meta ?? [];
            $deletedBy = $meta['deleted_by'] ?? [];
            if (! in_array((int) $user->id, $deletedBy)) {
                $deletedBy[] = (int) $user->id;
                $meta['deleted_by'] = $deletedBy;
                $message->meta = $meta;
                $message->save();
            }
        }

        return response()->json([
            'success' => true,
            'message' => __('Pesan berhasil dihapus'),
        ]);
    }

    public function starMessage($messageId)
    {
        $user = Auth::user();
        $message = Message::find($messageId);

        if (! $message) {
            return response()->json(['success' => false, 'message' => __('Pesan tidak ditemukan')], 404);
        }

        $inbox = Inbox::find($message->inbox_id, ['*']);
        if (! $inbox) {
            return response()->json(['success' => false, 'message' => __('Kotak pesan tidak ditemukan')], 404);
        }

        $userIds = $inbox->user_ids ?? [];
        if (! in_array((int) $user->id, array_map('intval', $userIds))) {
            return response()->json(['success' => false, 'message' => __('Tidak terautentikasi')], 403);
        }

        $meta = $message->meta ?? [];
        $starredBy = $meta['starred_by'] ?? [];
        $userIdStr = (string) $user->id;

        if (in_array($userIdStr, $starredBy)) {
            $starredBy = array_values(array_filter($starredBy, fn ($id) => $id !== $userIdStr));
            $isStarred = false;
        } else {
            $starredBy[] = $userIdStr;
            $isStarred = true;
        }

        $meta['starred_by'] = $starredBy;
        $message->meta = $meta;
        $message->save();

        return response()->json([
            'success' => true,
            'data' => [
                'is_starred' => $isStarred,
                'starred_by' => $starredBy,
            ],
        ]);
    }

    public function forwardMessage(Request $request, $messageId)
    {
        $request->validate([
            'target_inbox_id' => 'required|integer',
        ]);

        $user = Auth::user();
        $message = Message::find($messageId);

        if (! $message) {
            return response()->json(['success' => false, 'message' => __('Pesan tidak ditemukan')], 404);
        }

        $sourceInbox = Inbox::find($message->inbox_id, ['*']);
        if (! $sourceInbox) {
            return response()->json(['success' => false, 'message' => __('Kotak pesan tidak ditemukan')], 404);
        }

        $sourceUserIds = $sourceInbox->user_ids ?? [];
        if (! in_array((int) $user->id, array_map('intval', $sourceUserIds))) {
            return response()->json(['success' => false, 'message' => __('Tidak terautentikasi')], 403);
        }

        $targetInbox = Inbox::find($request->input('target_inbox_id'), ['*']);
        if (! $targetInbox) {
            return response()->json(['success' => false, 'message' => __('Kotak pesan target tidak ditemukan')], 404);
        }

        $targetUserIds = $targetInbox->user_ids ?? [];
        if (! in_array((int) $user->id, array_map('intval', $targetUserIds))) {
            return response()->json(['success' => false, 'message' => __('Tidak terautentikasi')], 403);
        }

        $forwardedMessage = Message::create([
            'inbox_id' => $targetInbox->id,
            'user_id' => $user->id,
            'message' => $message->message,
            'meta' => [
                'forwarded_from' => [
                    'message_id' => $message->id,
                    'sender_id' => $message->user_id,
                    'inbox_id' => $message->inbox_id,
                ],
            ],
        ]);

        if ($message->attachments->count() > 0) {
            foreach ($message->attachments as $attachment) {
                $forwardedMessage->addMedia($attachment)->toMediaCollection(MediaCollectionType::FILAMENT_MESSAGES->value);
            }
        }

        $forwardedMessage->load('attachments');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $forwardedMessage->id,
                'inbox_id' => $forwardedMessage->inbox_id,
                'user_id' => $forwardedMessage->user_id,
                'message' => $forwardedMessage->message,
                'meta' => $forwardedMessage->meta,
                'created_at' => $forwardedMessage->created_at->toIso8601String(),
                'attachments' => $forwardedMessage->attachments->map(fn (Media $m) => [
                    'id' => $m->id,
                    'url' => str_replace('/storage/', '/media/', $m->getFullUrl()),
                    'original_url' => str_replace('/storage/', '/media/', $m->getFullUrl()),
                    'name' => $m->name,
                    'size' => $m->size,
                    'mime_type' => $m->mime_type,
                ]),
            ],
        ], 201);
    }

    public function addReaction(Request $request, $messageId)
    {
        $request->validate([
            'emoji' => 'required|string|max:10',
        ]);

        $user = Auth::user();
        $message = Message::find($messageId);

        if (! $message) {
            return response()->json(['success' => false, 'message' => __('Pesan tidak ditemukan')], 404);
        }

        $inbox = Inbox::find($message->inbox_id, ['*']);
        if (! $inbox) {
            return response()->json(['success' => false, 'message' => __('Kotak pesan tidak ditemukan')], 404);
        }

        $userIds = $inbox->user_ids ?? [];
        if (! in_array((int) $user->id, array_map('intval', $userIds))) {
            return response()->json(['success' => false, 'message' => __('Tidak terautentikasi')], 403);
        }

        $meta = $message->meta ?? [];
        $reactions = $meta['reactions'] ?? [];
        $emoji = $request->input('emoji');
        $userIdStr = (string) $user->id;

        $userReactions = collect($reactions)->where('user_id', $userIdStr)->values();

        $existingIndex = $userReactions->search(fn ($r) => $r['emoji'] === $emoji);

        if ($existingIndex !== false) {
            $reactions = array_values(array_filter($reactions, function ($r) use ($userIdStr, $emoji) {
                return ! ($r['user_id'] === $userIdStr && $r['emoji'] === $emoji);
            }));
            $reacted = false;
        } else {
            $reactions = array_values(array_filter($reactions, function ($r) use ($userIdStr) {
                return $r['user_id'] !== $userIdStr;
            }));
            $reactions[] = [
                'user_id' => $userIdStr,
                'emoji' => $emoji,
            ];
            $reacted = true;
        }

        $meta['reactions'] = $reactions;
        $message->meta = $meta;
        $message->save();

        return response()->json([
            'success' => true,
            'data' => [
                'reacted' => $reacted,
                'emoji' => $emoji,
                'reactions' => $reactions,
            ],
        ]);
    }

    public function markInboxAsRead($inboxId)
    {
        $user = Auth::user();
        $inbox = Inbox::find($inboxId, ['*']);

        if (! $inbox) {
            return response()->json(['success' => false, 'message' => __('Kotak pesan tidak ditemukan')], 404);
        }

        $userIds = $inbox->user_ids ?? [];
        if (! in_array((int) $user->id, array_map('intval', $userIds))) {
            return response()->json(['success' => false, 'message' => __('Tidak terautentikasi')], 403);
        }

        $userIdStr = (string) $user->id;

        $unreadMessages = Message::where('inbox_id', $inbox->id)
            ->where('user_id', '!=', $user->id)
            ->where(function ($q) use ($userIdStr) {
                $q->whereNull('read_by')
                    ->orWhereNotJsonContains('read_by', $userIdStr);
            })
            ->get(['id', 'read_by']);

        foreach ($unreadMessages as $msg) {
            $readBy = $msg->read_by ?? [];
            if (! in_array($userIdStr, $readBy)) {
                $readBy[] = $userIdStr;
                $msg->read_by = $readBy;
                $msg->save();
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'marked_count' => $unreadMessages->count(),
            ],
        ]);
    }

    public function rateInbox(Request $request, $inboxId)
    {
        $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
        $inbox = Inbox::find($inboxId, ['*']);

        if (! $inbox) {
            return response()->json(['success' => false, 'message' => __('Kotak pesan tidak ditemukan')], 404);
        }

        $userIds = $inbox->user_ids ?? [];
        if (! in_array((int) $user->id, array_map('intval', $userIds))) {
            return response()->json(['success' => false, 'message' => __('Tidak terautentikasi')], 403);
        }

        $meta = $inbox->meta ?? [];
        $meta['cs_rating'] = [
            'user_id' => (int) $user->id,
            'rating' => (int) $request->input('rating'),
            'comment' => $request->input('comment', null),
            'rated_at' => now()->toIso8601String(),
        ];
        $inbox->meta = $meta;
        $inbox->save();

        return response()->json([
            'success' => true,
            'data' => [
                'cs_rating' => $meta['cs_rating'],
            ],
        ]);
    }

    /**
     * Start a CS conversation without login, identified by a device guest_id.
     */
    public function guestStart(Request $request)
    {
        $request->validate([
            'guest_id' => 'required|string|max:190',
            'name' => 'nullable|string|max:100',
            'cs_category' => 'nullable|string|max:50',
        ]);

        $guestId = $request->input('guest_id');
        $guest = $this->resolveGuestUser($guestId, $request->input('name'));
        $inbox = ChatService::getOrCreateInboxWithAdmin($guest->id);

        $inboxMeta = $inbox->meta ?? [];
        if (($inboxMeta['guest_id'] ?? null) !== $guestId) {
            $inboxMeta['guest_id'] = $guestId;
            $inbox->meta = $inboxMeta;
            $inbox->save();
        }

        if ($request->filled('cs_category')) {
            if (empty($inboxMeta['cs_category'])) {
                $inboxMeta['cs_category'] = $request->input('cs_category');
                $inbox->meta = $inboxMeta;
                $inbox->save();
            }
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $inbox->id,
            ],
        ], 201);
    }

    /**
     * Fetch messages for a guest conversation.
     */
    public function guestMessages(Request $request, $inboxId)
    {
        $request->validate([
            'guest_id' => 'required|string|max:190',
        ]);

        $guest = $this->resolveGuestUser($request->input('guest_id'), null);
        $inbox = Inbox::find($inboxId, ['*']);
        if (! $inbox) {
            return response()->json(['success' => false, 'message' => __('Kotak pesan tidak ditemukan')], 404);
        }

        $userIds = array_map('intval', $inbox->user_ids ?? []);
        if (! in_array((int) $guest->id, $userIds)) {
            return response()->json(['success' => false, 'message' => __('Tidak terautentikasi')], 403);
        }

        $adminUser = User::whereHas('roles', function ($q) {
            $q->where('name', 'super_admin');
        })->first(['id', 'full_name', 'username', 'avatar_url']);
        $adminId = $adminUser?->id ?? 1;

        $otherId = collect($userIds)->first(fn ($id) => (int) $id !== (int) $guest->id);
        $otherUser = $otherId ? User::find($otherId, ['id', 'full_name', 'username', 'avatar_url']) : null;

        $messages = Message::where('inbox_id', $inbox->id)
            ->with(['sender:id,full_name,username,avatar_url', 'attachments'])
            ->oldest()
            ->get(['*']);

        $data = $messages->map(function (Message $message) use ($guest) {
            $sender = $message->sender;

            return [
                'id' => $message->id,
                'message' => $message->message,
                'sender_id' => $message->user_id,
                'sender_name' => $sender?->full_name ?? $sender?->username ?? __('Tamu'),
                'is_me' => (int) $message->user_id === (int) $guest->id,
                'read_by' => $message->read_by ?? [],
                'attachments' => $message->attachments->map(fn (Media $m) => [
                    'id' => $m->id,
                    'url' => str_replace('/storage/', '/media/', $m->getFullUrl()),
                    'original_url' => str_replace('/storage/', '/media/', $m->getFullUrl()),
                    'name' => $m->name,
                    'size' => $m->size,
                    'mime_type' => $m->mime_type,
                ])->values(),
                'meta' => $message->meta,
                'created_at' => $message->created_at->toIso8601String(),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
            'other_user' => $otherUser ? [
                'id' => $otherUser->id,
                'name' => $otherUser->full_name ?: $otherUser->username ?? __('Admin'),
                'profile_photo' => $otherUser->avatar_url ?? null,
            ] : [
                'id' => $adminId,
                'name' => $adminUser?->full_name ?? __('Admin'),
                'profile_photo' => $adminUser?->avatar_url ?? null,
            ],
        ]);
    }

    /**
     * Send a message as guest into a guest conversation.
     */
    public function guestSend(Request $request)
    {
        $request->validate([
            'guest_id' => 'required|string|max:190',
            'inbox_id' => 'required',
            'message' => 'required|string',
            'cs_category' => 'nullable|string|max:50',
        ]);

        $guest = $this->resolveGuestUser($request->input('guest_id'), null);
        $inbox = Inbox::find($request->input('inbox_id'), ['*']);
        if (! $inbox) {
            return response()->json(['success' => false, 'message' => __('Kotak pesan tidak ditemukan')], 404);
        }

        $userIds = array_map('intval', $inbox->user_ids ?? []);
        if (! in_array((int) $guest->id, $userIds)) {
            return response()->json(['success' => false, 'message' => __('Tidak terautentikasi')], 403);
        }

        $message = Message::create([
            'inbox_id' => $inbox->id,
            'user_id' => $guest->id,
            'message' => $request->input('message', ''),
            'meta' => ['source' => 'guest'],
        ]);

        if ($request->filled('cs_category')) {
            $inboxMeta = $inbox->meta ?? [];
            if (empty($inboxMeta['cs_category'])) {
                $inboxMeta['cs_category'] = $request->input('cs_category');
                $inbox->meta = $inboxMeta;
                $inbox->save();
            }
        }

        SendBotReply::dispatch($message->id)->delay(now()->addSeconds(5));
        $message->load('attachments');

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $message->id,
                'inbox_id' => $message->inbox_id,
                'user_id' => $message->user_id,
                'message' => $message->message,
                'meta' => $message->meta,
                'created_at' => $message->created_at->toIso8601String(),
                'attachments' => $message->attachments->map(fn (Media $m) => [
                    'id' => $m->id,
                    'url' => str_replace('/storage/', '/media/', $m->getFullUrl()),
                    'original_url' => str_replace('/storage/', '/media/', $m->getFullUrl()),
                    'name' => $m->name,
                    'size' => $m->size,
                    'mime_type' => $m->mime_type,
                ]),
            ],
        ], 201);
    }

    /**
     * Resolve (or create) the guest user bound to a device guest_id.
     */
    private function resolveGuestUser(string $guestId, ?string $name = null): User
    {
        $email = 'guest_'.md5($guestId).'@guest.local';

        $guest = User::where('email', $email)->first();
        if (! $guest) {
            $guest = User::create([
                'email' => $email,
                'username' => 'tamu_'.substr(md5($guestId), 0, 8),
                'full_name' => ($name !== null && $name !== '') ? $name : __('Tamu'),
                'password' => Hash::make(Str::random(40)),
            ]);
        }

        return $guest;
    }
}
