<?php

namespace App\Filament\User\Auth\CompleteProfile;

use App\Filament\User\Auth\Concerns\HasAuthBreadcrumbs;
use App\Filament\User\Pages\Home\Home;
use Filament\Pages\Page;
use Illuminate\Contracts\Support\Htmlable;

class CompleteProfilePage extends Page
{
    use HasAuthBreadcrumbs;
    protected static string $view = 'User.auth.complete-profile.complete-profile';

    // Gaya Auth: layout simple fullscreen seperti SignIn/VerifyOtp,
    // tanpa top-navigation. Tetap extends Page agar bisa didaftar via
    // ->pages() (SimplePage murni tidak punya registerRoutes untuk itu).
    protected static string $layout = 'filament-panels::components.layout.simple';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $maxWidth = 'md';

    public function mount(): void
    {
        $user = auth()->user();

        // Sudah lengkap -> langsung ke home, jangan stuck di form.
        if ($user && method_exists($user, 'isProfileComplete') && $user->isProfileComplete()) {
            $this->redirect(Home::getUrl());
        }
    }

    public static function getSlug(): string
    {
        return 'complete-profile';
    }

    public function getHeading(): string|Htmlable
    {
        return __('Lengkapi Profil Anda');
    }

    public function getSubheading(): string|Htmlable|null
    {
        return __('Akun Anda belum memiliki data lengkap. Silakan lengkapi profil terlebih dahulu sebelum ke beranda.');
    }

    public function getTitle(): string
    {
        return __('Lengkapi Profil Anda');
    }

    public function hasLogo(): bool
    {
        return true;
    }
}
