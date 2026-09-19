<?php

namespace App\Filament\User\Pages\HelpCenterPage;

use App\Models\Help\Help;
use Filament\Pages\Page;

class HelpCenterPage extends Page
{
    protected static string $view = 'User.pages.help-center-page.help-center-page';

    protected static bool $shouldRegisterNavigation = false;

    public function getTitle(): string
    {
        return __('Pusat Bantuan');
    }

    public static function getNavigationLabel(): string
    {
        return __('Pusat Bantuan');
    }

    public static function getSlug(): string
    {
        return 'help-center';
    }

    protected function getViewData(): array
    {
        $help = Help::first();

        $faqs = $help?->faqs ?? [];
        if (is_string($faqs)) {
            $faqs = json_decode($faqs, true) ?? [];
        }

        return [
            'subtitle' => $help?->subtitle,
            'faqs' => $faqs,
            'contactOptions' => $help?->contact_options ?? [],
        ];
    }
}
