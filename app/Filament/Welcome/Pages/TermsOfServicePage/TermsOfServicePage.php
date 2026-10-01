<?php

namespace App\Filament\Welcome\Pages\TermsOfServicePage;

use App\Filament\Welcome\Pages\Home\Home;
use App\Models\TermsOfService\TermsOfService;
use Filament\Pages\Page;

class TermsOfServicePage extends Page
{
    protected static string $view = 'Welcome.pages.terms-of-service-page.terms-of-service-page';

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string
    {
        return __('Ketentuan Layanan');
    }

    public static function getNavigationLabel(): string
    {
        return __('Ketentuan Layanan');
    }

    public static function getSlug(): string
    {
        return 'terms-of-service';
    }

    protected function getViewData(): array
    {
        $terms = TermsOfService::first();

        $content = $terms?->content ?? [];
        if (is_string($content)) {
            $content = json_decode($content, true) ?? [];
        }

        return [
            'document' => [
                'title' => $terms?->title ?? __('Ketentuan Layanan'),
                'updated_at' => $terms?->updated_at,
                'content' => $content,
            ],
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            Home::getUrl() => __('Beranda'),
            $this->getTitle(),
        ];
    }
}
