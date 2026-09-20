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
use Native\Laravel\Notification;
use Native\Mobile\Dialog;

class PlatformNotificationService
{
    /**
     * Send a cross-platform notification to a user.
     *
     * Pass pre-translated $title / $body strings (already built with the
     * recipient's locale via withRecipientLocale()).
     *
     * If 'runtime.platform' is bound in the container, desktop/mobile
     * notification channels are gated by PlatformFeatureRegistry before
     * attempting the NativePHP API calls.  When the binding is absent the
     * service falls back to the original behavior (attempt anyway).
     */
    public static function send(User $user, string $title, string $body, ?string $actionUrl = null, ?string $actionLabel = null): void
    {
        // 1. Filament database notification (website — all browsers)
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

        // 2. Broadcast via WebSocket (real-time push to connected clients)
        event(new NotificationBroadcast([
            'title' => $title,
            'message' => $body,
            'type' => 'notification',
            'created_at' => now()->toISOString(),
        ], $user->id));

        // Resolve the current runtime platform once (may be null).
        $runtimePlatform = static::resolveRuntimePlatform();

        // 2. NativePHP desktop notification (desktop app)
        if (static::isDesktopNotificationAvailable($runtimePlatform)) {
            try {
                if (class_exists(Notification::class)) {
                    Notification::new()
                        ->title($title)
                        ->message(strip_tags($body))
                        ->show();
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // 3. NativePHP mobile toast notification (Android / iOS app)
        if (static::isMobileNotificationAvailable($runtimePlatform)) {
            try {
                if (class_exists(Dialog::class)) {
                    Dialog::toast(strip_tags($body), 'long');
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // 4. FCM push notification (Android / iOS physical device)
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

    /**
     * Determine whether the desktop notification channel should be attempted.
     *
     * - When $platform is null (no binding), fall through and attempt anyway
     *   (legacy behavior).
     * - When $platform is set, consult PlatformFeatureRegistry.
     */
    private static function isDesktopNotificationAvailable(?RuntimePlatform $platform): bool
    {
        if ($platform === null) {
            return true;
        }

        try {
            $registry = app(PlatformFeatureRegistry::class);
            $available = $registry->isAvailable('desktop_notifications', $platform);

            if (! $available) {
                Log::info('PlatformNotificationService: desktop_notifications channel skipped', [
                    'platform' => $platform->value,
                    'reason' => 'Feature not available on this platform',
                ]);
            }

            return $available;
        } catch (\Throwable) {
            // Registry unavailable — fall back to attempting the channel.
            return true;
        }
    }

    /**
     * Determine whether the mobile notification channel should be attempted.
     *
     * - When $platform is null (no binding), fall through and attempt anyway
     *   (legacy behavior).
     * - When $platform is set, consult PlatformFeatureRegistry.
     */
    private static function isMobileNotificationAvailable(?RuntimePlatform $platform): bool
    {
        if ($platform === null) {
            return true;
        }

        try {
            $registry = app(PlatformFeatureRegistry::class);
            $available = $registry->isAvailable('push_notifications', $platform);

            if (! $available) {
                Log::info('PlatformNotificationService: push_notifications channel skipped', [
                    'platform' => $platform->value,
                    'reason' => 'Feature not available on this platform',
                ]);
            }

            return $available;
        } catch (\Throwable) {
            // Registry unavailable — fall back to attempting the channel.
            return true;
        }
    }
}
