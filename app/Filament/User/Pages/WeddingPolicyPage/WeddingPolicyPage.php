<?php

namespace App\Filament\User\Pages\WeddingPolicyPage;

use App\Filament\Concerns\HasDynamicBreadcrumbs;
use App\Filament\User\Pages\Home\Home;
use App\Models\WeddingDecorationPolicy\WeddingDecorationPolicy;
use Filament\Pages\Page;

class WeddingPolicyPage extends Page
{
    use HasDynamicBreadcrumbs;

    protected static string $view = 'User.pages.wedding-policy-page.wedding-policy-page';

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string
    {
        return __('Kebijakan Aplikasi');
    }

    public static function getNavigationLabel(): string
    {
        return __('Kebijakan Aplikasi');
    }

    public static function getSlug(): string
    {
        return 'wedding-policy';
    }

    protected function getViewData(): array
    {
        $policy = WeddingDecorationPolicy::first();

        $content = $policy?->content ?? [];
        if (is_string($content)) {
            $content = json_decode($content, true) ?? [];
        }

        return [
            'document' => [
                'title' => $policy?->title ?? __('Kebijakan Aplikasi'),
                'updated_at' => $policy?->updated_at,
                'content' => $content,
            ],
        ];
    }

    public function getBreadcrumbs(): array
    {
        return [
            ...$this->breadcrumbParentCrumb(),
            $this->getTitle(),
        ];
    }
}
