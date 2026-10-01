<?php

namespace App\Filament\User\Pages\PrivacyTermsPage;

use App\Filament\User\Pages\Home\Home;
use App\Filament\User\Pages\PrivacyPolicyPage\PrivacyPolicyPage;
use App\Filament\User\Pages\TermsOfServicePage\TermsOfServicePage;
use App\Filament\User\Pages\WeddingPolicyPage\WeddingPolicyPage;
use Filament\Pages\Page;

class PrivacyTermsPage extends Page
{
    protected static string $view = 'User.pages.privacy-terms-page.privacy-terms-page';

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string
    {
        return __('Privasi & Ketentuan');
    }

    public static function getNavigationLabel(): string
    {
        return __('Privasi & Ketentuan');
    }

    public static function getSlug(): string
    {
        return 'privacy-terms';
    }

    public function getPrivacyPolicyUrl(): string
    {
        return PrivacyPolicyPage::getUrl();
    }

    public function getTermsOfServiceUrl(): string
    {
        return TermsOfServicePage::getUrl();
    }

    public function getWeddingPolicyUrl(): string
    {
        return WeddingPolicyPage::getUrl();
    }

    public function getBreadcrumbs(): array
    {
        return [
            Home::getUrl() => __('Beranda'),
            $this->getTitle(),
        ];
    }
}
