<?php

namespace App\Filament\User\Auth\SecurityQuestion;

use App\Filament\User\Auth\Concerns\HasAuthBreadcrumbs;
use App\Filament\User\Auth\TwoFactorAuth\TwoFactorAuth;
use App\Filament\User\Pages\Home\Home;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Satu kali Pondasi Keamanan, dijalankan setelah OTP verifikasi dan setelah
 * Complete Profile.
 *
 *   Sign In biasa  --> LANGSUNG Home
 *   (yang 2FA-nya sudah aktif saja yang diminta kode, itu route
 *    o-t-p-verify milik plugin multi-2fa)
 *
 *   Setelah OTP verifikasi   --+
 *   Setelah Complete Profile --+--> SecurityQuestion
 *                                        |-- Tidak --> Home
 *                                        `-- Ya    --> TwoFactorAuth --> Home
 *
 * Tidak pernah ditanyakan di setiap Sign In. Halaman ini hanya dituju dari
 * dua alur di atas, dan sekali menjawab langsung dicatat di session supaya
 * tidak muncul lagi.
 */
class SecurityQuestion extends Page
{
    use HasAuthBreadcrumbs;

    protected static string $view = 'User.auth.security-question.security-question';

    // Gaya Auth: layout simple fullscreen seperti SignIn/VerifyOtp,
    // tanpa top-navigation. Itu juga yang membuat tombol bahasa (yang
    // menempel di topbar) tidak ikut muncul di halaman ini.
    protected static string $layout = 'filament-panels::components.layout.simple';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $maxWidth = 'md';

    public function mount(): void
    {
        // Sudah menjawab di alur ini sebelumnya (mis. refresh, atau kembali
        // dari TwoFactorAuth) -- jangan tanya lagi.
        if (session()->pull('security_question_answered', false)) {
            $this->redirect(Home::getUrl());
        }
    }

    public static function getSlug(): string
    {
        return 'security-question';
    }

    public function getHeading(): string|Htmlable
    {
        return __('Perkuat Keamanan Akun Anda');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Dengan verifikasi dua langkah, akun Anda tetap bisa dipakai orang lain meskipun kata sandinya bocor.');
    }

    public function getMaxWidth(): ?string
    {
        return 'md';
    }

    /**
     * Jawaban tidak: tidak apa-apa, langsung ke Home.
     */
    public function skipAction(): Action
    {
        return Action::make('skip')
            ->label(__('Tidak, Nanti Saja'))
            ->color('gray')
            ->outlined()
            ->action(function (): void {
                session()->put('security_question_answered', true);

                $this->redirect(Home::getUrl());
            });
    }

    /**
     * Jawaban ya: masuk ke halaman aktivasi 2FA.
     */
    public function strengthenAction(): Action
    {
        return Action::make('strengthen')
            ->label(__('Ya, Perkuat Akun Saya'))
            ->action(function (): void {
                session()->put('security_question_answered', true);

                $this->redirect(TwoFactorAuth::getUrl());
            });
    }
}
