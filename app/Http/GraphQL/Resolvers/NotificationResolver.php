<?php

namespace App\Http\GraphQL\Resolvers;

use App\Models\User\User;
use App\Models\UserFcmDevice\UserFcmDevice;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Nuwave\Lighthouse\Exceptions\ValidationException;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class NotificationResolver
{
    public function index($root, array $args, GraphQLContext $context): array
    {
        $user = $context->user();

        if (! $user) {
            return [
                'data' => [],
                'currentPage' => 1,
                'lastPage' => 1,
                'perPage' => (int) ($args['first'] ?? 20),
                'total' => 0,
                'unreadCount' => 0,
            ];
        }

        $perPage = min(max((int) ($args['first'] ?? 20), 1), 100);

        /** @var Builder<\Illuminate\Notifications\DatabaseNotification> $query */
        $query = $user->notifications();

        if (! empty($args['unreadOnly'])) {
            $query->whereNull('read_at');
        }

        $paginator = $query->latest('created_at')->paginate($perPage, ['*'], 'page', (int) ($args['page'] ?? 1));

        return [
            'data' => array_map(
                fn ($notification): array => $this->present($notification),
                $paginator->items()
            ),
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
            'unreadCount' => $user->unreadNotifications()->count(),
        ];
    }

    public function unreadCount($root, array $args, GraphQLContext $context): int
    {
        $user = $context->user();

        return $user ? $user->unreadNotifications()->count() : 0;
    }

    /**
     * `title`, `body` and the readable `type` live inside the notification's
     * json `data` column rather than on the row, so they are flattened here.
     * Returning a plain array with camelCase keys also keeps the GraphQL type
     * free of per-field resolvers.
     */
    private function present(mixed $notification): array
    {
        $data = $this->payload($notification);

        return [
            'id' => (string) $notification->id,
            'type' => $this->readableType($data),
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? $data['message'] ?? null,
            'data' => $data,
            'readAt' => $notification->read_at?->toIso8601String(),
            'createdAt' => $notification->created_at?->toIso8601String(),
            'updatedAt' => $notification->updated_at?->toIso8601String(),
        ];
    }

    private function readableType(array $data): ?string
    {
        $type = $data['type'] ?? null;

        if (! is_string($type) || $type === '') {
            return null;
        }

        // HasNotificationChannels stores `static::class` in `data.type`. A
        // fully-qualified PHP class name is meaningless (and leaky) on the
        // client, so it is dropped and the client falls back to its own
        // icon/routing defaults.
        if (str_contains($type, '\\')) {
            return null;
        }

        return $type;
    }

    public function markAsRead($root, array $args, GraphQLContext $context): bool
    {
        $user = $this->authorizedUser($context);

        $notification = $user->notifications()->findOrFail($args['id']);
        $notification->markAsRead();

        return true;
    }

    public function markAllAsRead($root, array $args, GraphQLContext $context): bool
    {
        $this->authorizedUser($context)->unreadNotifications()->update(['read_at' => now()]);

        return true;
    }

    public function destroy($root, array $args, GraphQLContext $context): bool
    {
        $notification = $this->authorizedUser($context)->notifications()->findOrFail($args['id']);
        $notification->delete();

        return true;
    }

    public function registerFcmToken($root, array $args, GraphQLContext $context): bool
    {
        $input = $args['input'];

        if (empty($input['token'])) {
            throw ValidationException::withMessages([
                'token' => __('validation.required', ['attribute' => 'token']),
            ]);
        }

        $platform = $input['platform'] ?? 'android';

        if (! in_array($platform, ['android', 'ios'], true)) {
            throw ValidationException::withMessages([
                'platform' => __('validation.in', ['attribute' => 'platform']),
            ]);
        }

        /** @var User $user */
        $user = $this->authorizedUser($context);

        UserFcmDevice::updateOrCreate(
            ['user_id' => $user->id, 'fcm_token' => $input['token']],
            [
                'platform' => $platform,
                'device_name' => $input['deviceName'] ?? null,
                'last_used_at' => now(),
            ]
        );

        $user->update(['fcm_token' => $input['token']]);

        return true;
    }

    private function authorizedUser(GraphQLContext $context): \Illuminate\Foundation\Auth\User
    {
        $user = $context->user();

        if (! $user) {
            throw new \Exception('Unauthorized: Please login to access notifications');
        }

        return $user;
    }

    private function payload(mixed $notification): array
    {
        $data = $notification->data ?? null;

        if ($data instanceof \JsonSerializable || is_object($data)) {
            $data = json_decode(json_encode($data), true);
        }

        if (! is_array($data)) {
            $decoded = json_decode((string) $data, true);
            $data = is_array($decoded) ? $decoded : [];
        }

        return $data;
    }
}
