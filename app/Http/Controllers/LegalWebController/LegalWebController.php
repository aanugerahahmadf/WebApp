<?php

namespace App\Http\Controllers\LegalWebController;

use App\Http\Controllers\Controller;

use App\Models\Help\Help;
use App\Models\PrivacyPolicy\PrivacyPolicy;
use App\Models\TermsOfService\TermsOfService;

class LegalWebController extends Controller
{
    public function terms()
    {
        $terms = TermsOfService::first();

        return view('User.legal.legal-page.legal-page', [
            'title' => $terms?->title ?? 'Perjanjian Pengguna',
            'sections' => $terms?->content ?? [],
            'updatedAt' => $terms?->updated_at,
        ]);
    }

    public function privacy()
    {
        $privacy = PrivacyPolicy::first();

        return view('User.legal.legal-page.legal-page', [
            'title' => $privacy?->title ?? 'Kebijakan Privasi',
            'sections' => $privacy?->content ?? [],
            'updatedAt' => $privacy?->updated_at,
        ]);
    }

    public function help()
    {
        $help = Help::first();

        return view('User.legal.help-page.help-page', [
            'title' => $help?->title ?? 'Pusat Bantuan',
            'subtitle' => $help?->subtitle,
            'faqs' => $help?->faqs ?? [],
            'contactOptions' => $help?->contact_options ?? null,
            'updatedAt' => $help?->updated_at,
        ]);
    }
}
