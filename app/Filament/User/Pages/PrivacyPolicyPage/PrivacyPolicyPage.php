<?php

namespace App\Filament\User\Pages\PrivacyPolicyPage;

use App\Models\PrivacyPolicy\PrivacyPolicy;
use Filament\Pages\Page;

class PrivacyPolicyPage extends Page
{
    protected static string $view = 'User.pages.privacy-policy-page.privacy-policy-page';

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string
    {
        return __('Kebijakan Privasi');
    }

    public static function getNavigationLabel(): string
    {
        return __('Kebijakan Privasi');
    }

    public static function getSlug(): string
    {
        return 'privacy-policy';
    }

    protected function getViewData(): array
    {
        $privacy = PrivacyPolicy::first();

        $content = $privacy?->content ?? [];
        if (is_string($content)) {
            $content = json_decode($content, true) ?? [];
        }

        return [
            'document' => [
                'title' => $privacy?->title ?? __('Kebijakan Privasi'),
                'updated_at' => $privacy?->updated_at,
                'content' => $content,
            ],
        ];
    }
}
