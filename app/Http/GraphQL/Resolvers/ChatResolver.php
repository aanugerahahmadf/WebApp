<?php

namespace App\Http\GraphQL\Resolvers;

use App\Enums\Messages\MediaCollectionType\MediaCollectionType;
use App\Jobs\SendBotReply\SendBotReply;
use App\Models\Inbox\Inbox;
use App\Models\Message\Message;
use App\Models\Order\Order;
use App\Models\User\User;
use App\Services\ChatService\ChatService;
use App\Support\ChatParticipantName\ChatParticipantName;
use App\Support\ChatStaff\ChatStaff;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\ValidationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * GraphQL surface for the chat feature.
 *
 * Attachment upload intentionally stays on the multipart REST endpoint:
 * Lighthouse file uploads need the `Upload` scalar over multipart/form-data,
 * which is a different transport from the JSON POST the mobile client uses for
 * every other operation.
 */
class ChatResolver
{
    public function conversations($root, array $args, GraphQLContext $context): array
    {
        $user = $context->user();

        if (! $user) {
            return ['data' => [], 'currentUserIsStaff' => false];
        }

        $inboxes = Inbox::whereJsonContains('user_ids', (int) $user->id, 'and', false)
            ->with(['messages' => fn ($query) => $query->latest()->limit(1)])
            ->get();

        $adminUser = ChatStaff::adminUser();
        $adminId = $adminUser?->getKey();
        $isAdmin = ChatStaff::isStaff($user);

        return [
            'data' => $inboxes->map(fn (Inbox $inbox): array => $this->presentConversation(
                $inbox,
                $user,
                $adminId,
                $isAdmin
            ))->values()->all(),
            'currentUserIsStaff' => $isAdmin,
        ];
    }

    public function messages($root, array $args, GraphQLContext $context): array
    {
        $user = $this->memberUser($context, (int) $args['inboxId']);

        $inbox = Inbox::findOrFail($args['inboxId']);
        $other = $this->otherParticipant($inbox, $user);

        $query = Message::where('inbox_id', $inbox->id)
            ->with(['sender:id,full_name,username,avatar_url', 'attachments'])
            ->oldest();

        if (! empty($args['first'])) {
            $query->limit(min((int) $args['first'], 200));
        }

        return [
            'data' => $query->get()->map(fn (Message $message): array => $this->presentMessage($message, (int) $user->id))->values()->all(),
            'otherUser' => $other,
        ];
    }

    public function unreadCount($root, array $args, GraphQLContext $context): int
    {
        $user = $context->user();

        if (! $user) {
            return 0;
        }

        $inboxIds = Inbox::whereJsonContains('user_ids', (int) $user->id, 'and', false)->pluck('id');

        return Message::whereIn('inbox_id', $inboxIds)
            ->where('user_id', '!=', $user->id)
            ->where(fn ($q) => $q->whereNull('read_by')->orWhereJsonDoesntContain('read_by', (string) $user->id))
            ->count();
    }

    public function sendMessage($root, array $args, GraphQLContext $context): array
    {
        $input = $args['input'];
        $user = $this->memberUser($context, (int) $input['inboxId']);

        $message = trim((string) ($input['message'] ?? ''));

        if ($message === '' && empty($input['orderId']) && empty($input['itemId'])) {
            throw ValidationException::withMessages([
                'message' => __('validation.required', ['attribute' => 'message']),
            ]);
        }

        $meta = $this->buildMeta($input);

        $created = Message::create([
            'inbox_id' => $input['inboxId'],
            'user_id' => $user->id,
            'message' => $message,
            'meta' => $meta,
        ]);

        if (! empty($input['csCategory']) && ! ChatStaff::isStaff($user)) {
            $inbox = $created->inbox;
            $inboxMeta = $inbox->meta ?? [];
            $inboxMeta['cs_category'] = $input['csCategory'];
            $inbox->meta = $inboxMeta;
            $inbox->save();
        }

        if (! ChatStaff::isStaff($user)) {
            SendBotReply::dispatch($created->id);
        }

        $created->load(['sender:id,full_name,username,avatar_url', 'attachments']);

        return $this->presentMessage($created, (int) $user->id);
    }

    public function startConversation($root, array $args, GraphQLContext $context): array
    {
        /** @var User $user */
        $user = $this->authorizedUser($context);
        $input = $args['input'];
        $adminId = ChatStaff::adminUser()?->getKey();

        $targetId = isset($input['withUserId']) ? (int) $input['withUserId'] : ($adminId ? (int) $adminId : null);

        if ($targetId === null) {
            throw new \Exception(__('Admin belum tersedia untuk chat.'), 503);
        }

        if ($targetId === (int) $user->id) {
            // Staff identities are a single account, so a staff member starting
            // a chat is pointed at any other user, matching the REST behaviour.
            $targetId = (int) (User::where('id', '!=', $user->id)->value('id') ?? 0);

            if ($targetId === 0) {
                throw ValidationException::withMessages([
                    'withUserId' => __('Tidak ada pengguna lain untuk memulai chat.'),
                ]);
            }
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

        if (! empty($input['orderId'])) {
            $order = Order::find((int) $input['orderId']);

            if ($order) {
                ChatService::sendOrderMessage($inbox, $order);
            }
        } elseif (! empty($input['type']) && ! empty($input['itemId'])) {
            ChatService::sendContextMessage($inbox, [
                'type' => $input['type'],
                'id' => (int) $input['itemId'],
                'name' => $input['itemName'] ?? '',
                'price' => $input['itemPrice'] ?? null,
                'image' => $input['itemImage'] ?? '',
            ]);
        }

        return $this->presentConversation($inbox->fresh(), $user, $adminId, ChatStaff::isStaff($user));
    }

    public function markInboxAsRead($root, array $args, GraphQLContext $context): bool
    {
        $user = $this->memberUser($context, (int) $args['inboxId']);
        $userIdStr = (string) $user->id;

        $unread = Message::where('inbox_id', $args['inboxId'])
            ->where('user_id', '!=', $user->id)
            ->where(fn ($q) => $q->whereNull('read_by')->orWhereJsonDoesntContain('read_by', $userIdStr))
            ->get(['id', 'read_by']);

        foreach ($unread as $message) {
            $readBy = $message->read_by ?? [];

            if (! in_array($userIdStr, $readBy)) {
                $readBy[] = $userIdStr;
                $message->read_by = $readBy;
                $message->save();
            }
        }

        return true;
    }

    public function rateInbox($root, array $args, GraphQLContext $context): bool
    {
        $rating = (int) $args['rating'];

        if ($rating < 1 || $rating > 5) {
            throw ValidationException::withMessages([
                'rating' => __('validation.between.numeric', [
                    'attribute' => 'rating',
                    'min' => 1,
                    'max' => 5,
                ]),
            ]);
        }

        $user = $this->memberUser($context, (int) $args['inboxId']);
        $inbox = Inbox::findOrFail($args['inboxId']);

        $meta = $inbox->meta ?? [];
        $meta['cs_rating'] = [
            'user_id' => (int) $user->id,
            'rating' => $rating,
            'comment' => $args['comment'] ?? null,
            'rated_at' => now()->toIso8601String(),
        ];
        $inbox->meta = $meta;
        $inbox->save();

        return true;
    }

    public function starMessage($root, array $args, GraphQLContext $context): bool
    {
        $message = $this->memberMessage($context, (int) $args['messageId']);
        $user = $context->user();
        $meta = $message->meta ?? [];
        $starredBy = $meta['starred_by'] ?? [];
        $userIdStr = (string) $user->id;

        if (in_array($userIdStr, $starredBy)) {
            $starredBy = array_values(array_filter($starredBy, fn ($id) => $id !== $userIdStr));
        } else {
            $starredBy[] = $userIdStr;
        }

        $meta['starred_by'] = $starredBy;
        $message->meta = $meta;
        $message->save();

        return true;
    }

    public function addReaction($root, array $args, GraphQLContext $context): bool
    {
        $emoji = (string) $args['emoji'];

        if ($emoji === '' || mb_strlen($emoji) > 10) {
            throw ValidationException::withMessages([
                'emoji' => __('validation.max.numeric', ['attribute' => 'emoji', 'max' => 10]),
            ]);
        }

        $message = $this->memberMessage($context, (int) $args['messageId']);
        $userIdStr = (string) $context->user()->id;

        $meta = $message->meta ?? [];
        $reactions = collect($meta['reactions'] ?? []);

        $alreadyReacted = $reactions->contains(
            fn ($reaction) => $reaction['user_id'] === $userIdStr && $reaction['emoji'] === $emoji
        );

        if ($alreadyReacted) {
            $remaining = $reactions->reject(
                fn ($reaction) => $reaction['user_id'] === $userIdStr && $reaction['emoji'] === $emoji
            )->values()->all();
        } else {
            $remaining = $reactions->reject(fn ($reaction) => $reaction['user_id'] === $userIdStr)->values()->all();
            $remaining[] = ['user_id' => $userIdStr, 'emoji' => $emoji];
        }

        $meta['reactions'] = $remaining;
        $message->meta = $meta;
        $message->save();

        return true;
    }

    public function deleteMessage($root, array $args, GraphQLContext $context): bool
    {
        $message = $this->memberMessage($context, (int) $args['messageId']);
        $user = $context->user();
        $deleteType = $args['deleteType'] ?? 'me';

        if ($deleteType === 'everyone') {
            if ((int) $message->user_id !== (int) $user->id && ! ChatStaff::isStaff($user)) {
                throw new \Exception(__('Tidak diizinkan'), 403);
            }

            $message->delete();

            return true;
        }

        $meta = $message->meta ?? [];
        $deletedBy = $meta['deleted_by'] ?? [];

        if (! in_array((int) $user->id, $deletedBy)) {
            $deletedBy[] = (int) $user->id;
            $meta['deleted_by'] = $deletedBy;
            $message->meta = $meta;
            $message->save();
        }

        return true;
    }

    public function forwardMessage($root, array $args, GraphQLContext $context): array
    {
        $message = $this->memberMessage($context, (int) $args['messageId']);
        $user = $context->user();

        $targetInbox = Inbox::find($args['targetInboxId']);

        if (! $targetInbox) {
            throw new \Exception(__('Kotak pesan target tidak ditemukan'), 404);
        }

        $targetUserIds = array_map('intval', $targetInbox->user_ids ?? []);

        if (! in_array((int) $user->id, $targetUserIds, true)) {
            throw new \Exception(__('Tidak terautentikasi'), 403);
        }

        $forwarded = Message::create([
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

        foreach ($message->attachments as $attachment) {
            $forwarded->addMedia($attachment)->toMediaCollection(MediaCollectionType::FILAMENT_MESSAGES->value);
        }

        $forwarded->load(['sender:id,full_name,username,avatar_url', 'attachments']);

        return $this->presentMessage($forwarded, (int) $user->id);
    }

    private function presentConversation(Inbox $inbox, User $user, ?int $adminId, bool $isAdmin): array
    {
        $lastMessage = $inbox->messages?->first();
        $other = $this->otherParticipant($inbox, $user);

        $unreadCount = Message::where('inbox_id', $inbox->id)
            ->where('user_id', '!=', $user->id)
            ->where(fn ($q) => $q->whereNull('read_by')->orWhereJsonDoesntContain('read_by', (string) $user->id))
            ->count();

        $title = $isAdmin
            ? ($other ? __('Chat dengan :name', ['name' => $other['name']]) : ($inbox->title ?? __('Chat Bantuan')))
            : __('Chat dengan Admin');

        return [
            'id' => (string) $inbox->id,
            'title' => $title,
            'otherUser' => $other ?? [
                'id' => $adminId !== null ? (string) $adminId : null,
                'name' => __('Admin'),
                'profilePhoto' => null,
                'phone' => null,
                'callUrl' => '',
            ],
            'lastMessage' => $lastMessage ? [
                'id' => (string) $lastMessage->id,
                'message' => $lastMessage->message,
                'createdAt' => $lastMessage->created_at->toIso8601String(),
                'senderId' => (string) $lastMessage->user_id,
            ] : null,
            'unreadCount' => $unreadCount,
            'csCategory' => $inbox->meta['cs_category'] ?? null,
            'createdAt' => $inbox->created_at->toIso8601String(),
            'updatedAt' => $inbox->updated_at->toIso8601String(),
        ];
    }

    private function otherParticipant(Inbox $inbox, User $user): ?array
    {
        $otherId = collect($inbox->user_ids ?? [])->first(fn ($id) => (int) $id !== (int) $user->id);

        if (! $otherId) {
            return null;
        }

        $otherUser = User::find($otherId, ['id', 'full_name', 'username', 'avatar_url', 'phone', 'whatsapp']);

        if (! $otherUser) {
            return null;
        }

        return [
            'id' => (string) $otherUser->id,
            'name' => ChatParticipantName::for($otherUser, __('Pengguna')),
            'profilePhoto' => $otherUser->avatar_url,
            'phone' => $otherUser->phone ?? $otherUser->whatsapp,
            'callUrl' => '',
        ];
    }

    private function presentMessage(Message $message, int $userId): array
    {
        return [
            'id' => (string) $message->id,
            'inboxId' => (string) $message->inbox_id,
            'message' => $message->message,
            'senderId' => (string) $message->user_id,
            'senderName' => ChatParticipantName::for($message->sender, __('Tidak dikenal')),
            'isMe' => (int) $message->user_id === $userId,
            'readBy' => array_map('strval', $message->read_by ?? []),
            'attachments' => $message->attachments->map(fn (Media $media): array => [
                'id' => (string) $media->id,
                'url' => str_replace('/storage/', '/media/', $media->getFullUrl()),
                'originalUrl' => str_replace('/storage/', '/media/', $media->getFullUrl()),
                'name' => $media->name,
                'size' => (int) $media->size,
                'mimeType' => $media->mime_type,
            ])->values()->all(),
            'meta' => $message->meta,
            'createdAt' => $message->created_at->toIso8601String(),
        ];
    }

    private function buildMeta(array $input): ?array
    {
        if (! empty($input['replyToId'])) {
            $replied = Message::find((int) $input['replyToId']);

            if ($replied) {
                return [
                    'reply_to' => [
                        'id' => $replied->id,
                        'message' => $replied->message,
                        'sender_name' => ChatParticipantName::for($replied->sender, __('Tidak dikenal')),
                        'sender_id' => $replied->user_id,
                    ],
                ];
            }

            return null;
        }

        if (! empty($input['orderId'])) {
            $order = Order::with(['package.media', 'catalog.media'])->find((int) $input['orderId']);

            if (! $order) {
                return null;
            }

            $firstMedia = $order->package?->media?->first() ?? $order->catalog?->media?->first();

            return [
                'is_order' => true,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
                'order_status' => $order->status,
                'payment_status' => $order->payment_status,
                'name' => $order->package?->name ?? $order->catalog?->name ?? '',
                'image' => $firstMedia ? str_replace('/storage/', '/media/', $firstMedia->getFullUrl()) : '',
            ];
        }

        if (! empty($input['type']) && ! empty($input['itemId'])) {
            return [
                'type' => $input['type'],
                'id' => (int) $input['itemId'],
                'name' => $input['itemName'] ?? '',
                'price' => $input['itemPrice'] ?? null,
                'image' => $input['itemImage'] ?? '',
            ];
        }

        return null;
    }

    private function authorizedUser(GraphQLContext $context): User
    {
        $user = $context->user();

        if (! $user instanceof User) {
            throw new \Exception('Unauthorized: Please login to use chat');
        }

        return $user;
    }

    /**
     * Every chat operation is scoped to an inbox the caller actually belongs
     * to; the REST controller returns 403 for outsiders and this mirrors it.
     */
    private function memberUser(GraphQLContext $context, int $inboxId): User
    {
        $user = $this->authorizedUser($context);
        $inbox = Inbox::findOrFail($inboxId);
        $userIds = array_map('intval', $inbox->user_ids ?? []);

        if (! in_array((int) $user->id, $userIds, true)) {
            throw new \Exception(__('Tidak terautentikasi'), 403);
        }

        return $user;
    }

    private function memberMessage(GraphQLContext $context, int $messageId): Message
    {
        $message = Message::find($messageId);

        if (! $message) {
            throw new \Exception(__('Pesan tidak ditemukan'), 404);
        }

        $this->memberUser($context, (int) $message->inbox_id);

        return $message;
    }
}
