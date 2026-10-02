<?php

namespace App\Filament\Welcome\Pages\PrivacyTermsPage;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\Welcome\Pages\Home\Home;
use App\Filament\Welcome\Pages\PrivacyPolicyPage\PrivacyPolicyPage;
use App\Filament\Welcome\Pages\TermsOfServicePage\TermsOfServicePage;
use App\Filament\Welcome\Pages\WeddingPolicyPage\WeddingPolicyPage;
use Filament\Pages\Page;

class PrivacyTermsPage extends Page
{
    use HasDynamicBreadcrumbs;

    protected static string $view = 'Welcome.pages.privacy-terms-page.privacy-terms-page';

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
            ...$this->breadcrumbParentCrumb(),
            $this->getTitle(),
        ];
    }
}
