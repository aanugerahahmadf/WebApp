<?php

namespace App\Services\PlatformNotificationService;


use App\Services\FirebaseService\FirebaseService;

use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Events\NotificationBroadcast\NotificationBroadcast;
use App\Models\User\User;
use App\Support\Platform\PlatformFeatureRegistry\PlatformFeatureRegistry;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Facades\Log;

class PlatformNotificationService
{
    /**
     * Send a cross-platform notification to a user.
     *
     * Pass pre-translated $title / $body strings (already built with the
     * recipient's locale via withRecipientLocale()).
     *
     * Delivery is the same on every surface: a Filament database record the
     * panel renders, plus a WebSocket broadcast that any open client — website,
     * Capacitor mobile shell or Electron desktop shell — picks up live. The
     * Capacitor shells are ordinary browser contexts, so an OS-level toast is
     * requested through the browser Notification API by the client, and real
     * push to a device that is not in the foreground goes out over FCM.
     */
    public static function send(User $user, string $title, string $body, ?string $actionUrl = null, ?string $actionLabel = null): void
    {
        // 1. Filament database notification (in-app inbox, every surface)
        $notification = FilamentNotification::make()
            ->title($title)
            ->body($body)
            ->warning();

        if ($actionUrl) {
            $notification->actions([
                Action::make('open')
                    ->label($actionLabel ?: __('Lihat detail'))
                    ->url($actionUrl)
                    ->markAsRead(),
            ]);
        }

        $notification->sendToDatabase($user);

        // 2. Broadcast via WebSocket (real-time delivery to open clients)
        event(new NotificationBroadcast([
            'title' => $title,
            'message' => $body,
            'type' => 'notification',
            'created_at' => now()->toISOString(),
        ], $user->id));

        // 3. FCM push notification (device not in the foreground)
        static::sendFcmPush($user, $title, $body);
    }

    public static function sendFcmPush(User $user, string $title, string $body, array $data = []): void
    {
        if (! $user->fcm_token) {
            return;
        }
        try {
            $firebase = app(FirebaseService::class);
            $firebase->sendPushNotification($user->fcm_token, $title, $body, $data);
        } catch (\Throwable $e) {
            Log::warning('FCM push skipped', ['error' => $e->getMessage(), 'user_id' => $user->id]);
        }
    }

    public static function sendFcmPushToAllDevices(User $user, string $title, string $body, array $data = [], ?string $excludeToken = null): void
    {
        $firebase = app(FirebaseService::class);
        if (! $firebase->isInitialized()) {
            Log::warning('FCM push skipped: Firebase not initialized', ['user_id' => $user->id]);

            return;
        }

        $devices = $user->fcmDevices()->get();
        Log::info('Sending 2FA notifications', [
            'user_id' => $user->id,
            'total_devices' => $devices->count(),
            'has_exclude_token' => $excludeToken !== null,
        ]);

        $sentCount = 0;
        foreach ($devices as $device) {
            if ($excludeToken && $device->fcm_token === $excludeToken) {
                Log::info('Skipping current device', ['platform' => $device->platform]);

                continue;
            }
            try {
                $result = $firebase->sendPushNotification($device->fcm_token, $title, $body, $data, [
                    'channel_id' => 'security_notifications',
                ]);
                if ($result) {
                    $sentCount++;
                }
            } catch (\Throwable $e) {
                Log::warning('FCM push to device failed', [
                    'error' => $e->getMessage(),
                    'user_id' => $user->id,
                    'platform' => $device->platform,
                ]);
            }
        }

        if ($devices->isEmpty() && $user->fcm_token) {
            Log::info('Fallback to legacy fcm_token', ['user_id' => $user->id]);
            try {
                $firebase->sendPushNotification($user->fcm_token, $title, $body, $data, [
                    'channel_id' => 'security_notifications',
                ]);
            } catch (\Throwable $e) {
                Log::warning('FCM push fallback failed', ['error' => $e->getMessage(), 'user_id' => $user->id]);
            }
        }

        Log::info('2FA notification send complete', ['user_id' => $user->id, 'sent_count' => $sentCount]);
    }

    /**
     * Run $callback with the app locale temporarily set to the recipient's
     * preferred language, then restore the original locale.
     *
     * Usage:
     *   [$title, $body] = PlatformNotificationService::withRecipientLocale(
     *       $user,
     *       fn () => [__('My title'), __('My body')]
     *   );
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function withRecipientLocale(User $user, callable $callback): mixed
    {
        $original = app()->getLocale();

        try {
            $recipientLocale = $user->lang ?? $original;
            app()->setLocale($recipientLocale);

            return $callback();
        } finally {
            app()->setLocale($original);
        }
    }

    /**
     * Send a notification using only the Filament database channel.
     *
     * Use this when only the web notification is needed (e.g. in contexts where
     * desktop/mobile channels must be explicitly skipped regardless of the
     * current runtime platform).
     */
    public static function sendToWebOnly(User $user, string $title, string $body): void
    {
        FilamentNotification::make()
            ->title($title)
            ->body($body)
            ->warning()
            ->sendToDatabase($user);

        event(new NotificationBroadcast([
            'title' => $title,
            'message' => $body,
            'type' => 'notification',
            'created_at' => now()->toISOString(),
        ], $user->id));
    }

    /**
     * Return the list of active notification channels for the given platform.
     *
     * Always includes 'database' (Filament / web).
     * Adds 'desktop' when the platform supports desktop_notifications.
     * Adds 'mobile' when the platform supports push_notifications.
     *
     * @return array<string> e.g. ['database'], ['database', 'desktop'], ['database', 'mobile']
     */
    public static function getActiveChannels(RuntimePlatform $platform): array
    {
        $channels = ['database'];

        try {
            $registry = app(PlatformFeatureRegistry::class);

            if ($registry->isAvailable('desktop_notifications', $platform)) {
                $channels[] = 'desktop';
            }

            if ($registry->isAvailable('push_notifications', $platform)) {
                $channels[] = 'mobile';
            }
        } catch (\Throwable) {
            // Registry unavailable — return only the guaranteed 'database' channel.
        }

        return $channels;
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Attempt to resolve the current RuntimePlatform from the IoC container.
     *
     * Returns null when 'runtime.platform' is not bound yet, which preserves
     * backward-compatibility: callers receive null and all channels are tried.
     */
    private static function resolveRuntimePlatform(): ?RuntimePlatform
    {
        try {
            if (app()->bound('runtime.platform')) {
                return app('runtime.platform');
            }
        } catch (\Throwable) {
            // Silently ignore — treat as if not bound.
        }

        return null;
    }
}
