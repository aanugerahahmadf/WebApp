<?php

namespace App\Filament\User\Pages\NotificationDetailPage;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\User\Pages\Home\Home;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\SecurityCheck\SignInActivity\SignInActivity;
use App\Filament\User\Pages\DeviceTrustPrompt\DeviceTrustPrompt;
use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;

class NotificationDetailPage extends Page
{
    use HasDynamicBreadcrumbs;

    protected static string $view = 'User.pages.notification-detail-page.notification-detail-page';

    protected static bool $shouldRegisterNavigation = false;

    /** @var array{title: string, body: string, icon: string, received_at: string, action_url: ?string, action_label: ?string} */
    public array $notificationDetail = [];

    public string $notificationId = '';

    public static function getSlug(): string
    {
        return 'notifications/{id}';
    }

    public function mount(string $id): void
    {
        $this->notificationId = $id;

        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $notification = DatabaseNotification::query()
            ->whereKey($id)
            ->where('notifiable_id', $user->getKey())
            ->where('notifiable_type', $user->getMorphClass())
            ->firstOrFail();

        $notification->markAsRead();

        $data = $notification->data;
        $firstAction = collect($data['actions'] ?? [])->first();
        $actionUrl = is_array($firstAction) && is_string($firstAction['url'] ?? null)
            ? $firstAction['url']
            : null;

        $this->notificationDetail = [
            'title' => __((string) ($data['title'] ?? __('Notifikasi'))),
            'body' => $this->localizeNotificationBody(
                (string) ($data['title'] ?? ''),
                (string) ($data['body'] ?? ''),
            ),
            'icon' => (string) ($data['icon'] ?? '/images/icons/bell_alert.svg'),
            'received_at' => $notification->created_at?->translatedFormat('d F Y, H:i:s') ?? '-',
            'action_url' => $this->withNotificationReturnUrl($actionUrl),
            'action_label' => is_array($firstAction) && is_string($firstAction['label'] ?? null) ? __($firstAction['label']) : null,
        ];
    }

    public function getTitle(): string
    {
        return __('Detail Notifikasi');
    }

    public function getHeading(): string
    {
        return __('Detail Notifikasi');
    }

    public function getSubheading(): ?string
    {
        return __('Informasi lengkap dari notifikasi yang Anda pilih.');
    }

    /**
     * Keep every detail page actionable, including notifications created before
     * action URLs were saved in their database payload.
     *
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        $actions = [];

        $actionUrl = $this->notificationDetail['action_url'] ?? null;
        $actionLabel = $this->notificationDetail['action_label'] ?? null;

        if ($actionUrl) {
            $actions[] = Action::make('openNotificationAction')
                ->label($actionLabel ?: __('Lihat detail'))
                ->icon('/images/icons/external.svg')
                ->url($actionUrl);
        } elseif ($this->isSignInNotification()) {
            $actions[] = Action::make('viewSignInActivity')
                ->label(__('Lihat aktivitas Sign In'))
                ->icon('/images/icons/shield_check.svg')
                ->url(SignInActivity::getUrl([
                    'returnTo' => static::getUrl(['id' => $this->notificationId], panel: 'user'),
                ], panel: 'user'));
        }

        return $actions;
    }

    private function isSignInNotification(): bool
    {
        $title = mb_strtolower($this->notificationDetail['title'] ?? '');

        return str_contains($title, 'login detected')
            || str_contains($title, 'login terdeteksi')
            || str_contains($title, 'apakah ini anda')
            || str_contains($title, 'sign in');
    }

    private function localizeNotificationBody(string $title, string $body): string
    {
        $lowerTitle = mb_strtolower($title);

        if (str_contains($lowerTitle, 'perangkat baru') || str_contains($lowerTitle, 'new device')) {
            if (preg_match('/^Sign in dari perangkat yang belum pernah dipakai oleh akun Anda: (.+), dari (.+) \((.+)\) pada (.+)\. Kalau ini bukan Anda, keluarkan semua sesi sekarang\.$/u', $body, $matches)) {
                return __('Sign in from a device that has not been used by your account: :device, from :ip (:location) at :time. If this was not you, sign out of all sessions now.', [
                    'device' => $matches[1], 'ip' => $matches[2], 'location' => $matches[3], 'time' => $matches[4],
                ]);
            }
        }

        if (str_contains($lowerTitle, 'login terdeteksi') || str_contains($lowerTitle, 'login detected')) {
            if (preg_match('/^Akun Anda telah digunakan dari (.+) \((.+), (.+)\) pada (.+)\.$/u', $body, $matches)) {
                return __('Your account was used from :device (:ip, :location) at :time.', [
                    'device' => $matches[1], 'ip' => $matches[2], 'location' => $matches[3], 'time' => $matches[4],
                ]);
            }
        }

        return __($body);
    }

    private function withNotificationReturnUrl(?string $actionUrl): ?string
    {
        $signInActivityUrl = SignInActivity::getUrl(panel: 'user');

        $deviceTrustUrl = url('/device-trust/');

        if (! $actionUrl) {
            return $actionUrl;
        }

        if (str_contains($actionUrl, 'returnTo=')) {
            return $actionUrl;
        }

        if (! str_starts_with($actionUrl, $signInActivityUrl) && ! str_starts_with($actionUrl, $deviceTrustUrl)) {
            return $actionUrl;
        }

        $separator = str_contains($actionUrl, '?') ? '&' : '?';

        return $actionUrl.$separator.http_build_query([
            'returnTo' => static::getUrl(['id' => $this->notificationId], panel: 'user'),
        ]);
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            $this->getTitle(),
        ];
    }
}
