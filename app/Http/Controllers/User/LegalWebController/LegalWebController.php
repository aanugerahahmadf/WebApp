<?php

namespace App\Http\Controllers\User\LegalWebController;

use App\Http\Controllers\Controller;
use App\Models\Help\Help;
use App\Models\PrivacyPolicy\PrivacyPolicy;
use App\Models\TermsOfService\TermsOfService;
use Illuminate\Contracts\View\View;

class LegalWebController extends Controller
{
    protected string $viewPrefix = 'User';

    protected function resolveView(string $path): string
    {
        $candidate = $this->viewPrefix.'.'.$path;

        if (view()->exists($candidate)) {
            return $candidate;
        }

        return 'User.'.$path;
    }

    public function terms(): View
    {
        $terms = TermsOfService::first();

        return view($this->resolveView('legal.legal-page.legal-page'), [
            'title' => $terms?->title ?? 'Perjanjian Pengguna',
            'sections' => $terms?->content ?? [],
            'updatedAt' => $terms?->updated_at,
        ]);
    }

    public function privacy(): View
    {
        $privacy = PrivacyPolicy::first();

        return view($this->resolveView('legal.legal-page.legal-page'), [
            'title' => $privacy?->title ?? 'Kebijakan Privasi',
            'sections' => $privacy?->content ?? [],
            'updatedAt' => $privacy?->updated_at,
        ]);
    }

    public function help(): View
    {
        $help = Help::first();

        return view($this->resolveView('legal.help-page.help-page'), [
            'title' => $help?->title ?? 'Pusat Bantuan',
            'subtitle' => $help?->subtitle,
            'faqs' => $help?->faqs ?? [],
            'contactOptions' => $help?->contact_options ?? null,
            'updatedAt' => $help?->updated_at,
        ]);
    }
}
