<?php

namespace App\Filament\User\Pages\NotificationDetailPage;

use App\Filament\User\Pages\Dashboard\Dashboard;
use App\Filament\User\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage;
use App\Models\User\User;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Auth;

class NotificationDetailPage extends Page
{
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
            'title' => (string) ($data['title'] ?? __('Notifikasi')),
            'body' => (string) ($data['body'] ?? ''),
            'icon' => (string) ($data['icon'] ?? 'heroicon-o-bell-alert'),
            'received_at' => $notification->created_at?->translatedFormat('d F Y, H:i:s') ?? '-',
            'action_url' => $this->withNotificationReturnUrl($actionUrl),
            'action_label' => is_array($firstAction) && is_string($firstAction['label'] ?? null) ? $firstAction['label'] : null,
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
        $actions = [
            Action::make('backToDashboard')
                ->label(__('Kembali ke Beranda'))
                ->icon('heroicon-m-arrow-left')
                ->color('gray')
                ->url(Dashboard::getUrl(panel: 'user')),
        ];

        $actionUrl = $this->notificationDetail['action_url'] ?? null;
        $actionLabel = $this->notificationDetail['action_label'] ?? null;

        if ($actionUrl) {
            $actions[] = Action::make('openNotificationAction')
                ->label($actionLabel ?: __('Lihat detail'))
                ->icon('heroicon-m-arrow-top-right-on-square')
                ->url($actionUrl);
        } elseif ($this->isSignInNotification()) {
            $actions[] = Action::make('viewSignInActivity')
                ->label(__('Lihat aktivitas Sign In'))
                ->icon('heroicon-m-shield-check')
                ->url(PasswordSecurityPage::getUrl([
                    'section' => 'sign-in-activity',
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
            || str_contains($title, 'sign in');
    }

    private function withNotificationReturnUrl(?string $actionUrl): ?string
    {
        $signInActivityUrl = PasswordSecurityPage::getUrl([
            'section' => 'sign-in-activity',
        ], panel: 'user');

        if (! $actionUrl || ! str_starts_with($actionUrl, $signInActivityUrl)) {
            return $actionUrl;
        }

        if (str_contains($actionUrl, 'returnTo=')) {
            return $actionUrl;
        }

        $separator = str_contains($actionUrl, '?') ? '&' : '?';

        return $actionUrl.$separator.http_build_query([
            'returnTo' => static::getUrl(['id' => $this->notificationId], panel: 'user'),
        ]);
    }
}
