<?php

namespace App\Filament\User\Pages\DeviceTrustPrompt;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\Welcome\Pages\Home\Home;
use App\Filament\Welcome\Pages\SettingsPage\PasswordSecurityPage\PasswordSecurityPage;
use App\Models\TrustedDevice\TrustedDevice;
use App\Models\User\User;
use App\Support\DeviceTrust\DeviceTrustService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

/*
 * Halaman tinjau perangkat ala Google "Review blocked sign-in".
 *
 * Dijangkau dari tombol "Tinjau Perangkat" yang menyertai notifikasi
 * "Perangkat Baru -- Apakah Ini Anda?" (DeviceTrustService). Di sini
 * rincian perangkat dijelaskan, lalu user memilih:
 *
 *   - "Ya, Ini Saya" -> perangkat dipercaya (trusted_at); tidak ditanya lagi.
 *   - "Bukan Saya"   -> sesi web lain + semua token HP dikeluarkan, lalu
 *                       diantar ke halaman Sandi & Keamanan untuk ganti
 *                       kata sandi.
 */
class DeviceTrustPrompt extends Page
{
    use HasDynamicBreadcrumbs;

    protected static string $view = 'User.pages.device-trust-prompt.device-trust-prompt';

    protected static bool $shouldRegisterNavigation = false;

    /**
     * Perangkat yang sedang ditinjau. Nama property sengaja BEDA dari
     * parameter mount: kalau sama ($device == $device), Livewire mencoba
     * meng-assign string id langsung ke property bertipe TrustedDevice
     * sebelum mount berjalan, dan halaman 500 (ViewException TypeError).
     */
    public ?TrustedDevice $reviewDevice = null;

    /** Perangkat sudah direspons? Nyala kembali tombol. */
    public bool $answered = false;

    public static function getSlug(): string
    {
        return 'device-trust/{id}';
    }

    /**
     * Notifikasi menyimpan URL absolut DeviceTrustPrompt::getUrl([...]).
     * Parameter route langsung membawa id baris trusted_devices.
     */
    public function mount(string $id): void
    {
        $user = Auth::user();

        abort_unless($user instanceof User, 403);

        $this->reviewDevice = TrustedDevice::query()
            ->whereKey($id)
            ->where('user_id', $user->getKey())
            ->firstOrFail();

        $this->answered = $this->reviewDevice->trusted_at !== null;
    }

    public function getTitle(): string
    {
        return __('Perangkat Baru');
    }

    public function getHeading(): string
    {
        return __('Apakah ini Anda?');
    }

    public function getSubheading(): ?string
    {
        return __('Kami melihat sign in dari perangkat yang belum pernah dipakai oleh akun ini. Periksa rinciannya di bawah.');
    }

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('backToHome')
                ->label(__('Kembali ke Beranda'))
                ->icon('/images/icons/arrow_left.svg')
                ->color('gray')
                ->url(Home::getUrl(panel: 'welcome')),
        ];
    }

    /**
     * "Ya, Ini Saya" -- tandai perangkat itu sebagai milik user.
     */
    public function confirmItIsMe(): void
    {
        DeviceTrustService::trust($this->reviewDevice, Auth::user());
        $this->answered = true;

        Notification::make()
            ->success()
            ->title(__('Perangkat Dipercaya'))
            ->body(__('Terima kasih sudah mengonfirmasi. Sign in berikutnya dari perangkat ini tidak diperiksa lagi.'))
            ->send();

        $this->redirect(Home::getUrl(panel: 'welcome'));
    }

    /**
     * "Bukan saya" -- keluarkan semua sesi web lain + token HP, lalu antar
     * ke halaman Sandi & Keamanan supaya kata sandi diganti segera.
     */
    public function denyItIsNotMe(): void
    {
        DeviceTrustService::deny($this->reviewDevice, Auth::user());
        $this->answered = true;

        Notification::make()
            ->warning()
            ->title(__('Semua Sesi Dikeluarkan'))
            ->body(__('Sesi web lain dan token aplikasi HP sudah dibersihkan. Silakan ubah kata sandi Anda sekarang.'))
            ->send();

        $this->redirect(PasswordSecurityPage::getUrl(panel: 'welcome'));
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            $this->getTitle(),
        ];
    }
}
