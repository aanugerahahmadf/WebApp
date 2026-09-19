<?php

namespace App\Filament\User\Pages\TermsOfServicePage;

use App\Models\TermsOfService\TermsOfService;
use Filament\Pages\Page;

class TermsOfServicePage extends Page
{
    protected static string $view = 'User.pages.terms-of-service-page.terms-of-service-page';

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
}
