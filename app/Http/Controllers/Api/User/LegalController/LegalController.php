<?php

namespace App\Http\Controllers\Api\User\LegalController;

use App\Http\Controllers\Controller;
use App\Models\Help\Help;
use App\Models\LegalPage\LegalPage;
use App\Models\PrivacyPolicy\PrivacyPolicy;
use App\Models\TermsOfService\TermsOfService;
use App\Models\WeddingDecorationPolicy\WeddingDecorationPolicy;
use Illuminate\Http\JsonResponse;

class LegalController extends Controller
{
    protected function locale(): string
    {
        return app()->getLocale();
    }

    public function getTerms(): JsonResponse
    {
        $terms = TermsOfService::first();

        if (! $terms) {
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => null,
                    'title' => 'Terms & Conditions',
                    'content' => null,
                    'updated_at' => null,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $terms->id,
                'title' => $terms->trans('title', $this->locale()),
                'content' => $terms->trans('content', $this->locale()),
                'updated_at' => $terms->updated_at?->format('d M Y'),
            ],
        ]);
    }

    public function getPrivacy(): JsonResponse
    {
        $privacy = PrivacyPolicy::first();

        if (! $privacy) {
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => null,
                    'title' => 'Privacy Policy',
                    'content' => null,
                    'updated_at' => null,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $privacy->id,
                'title' => $privacy->trans('title', $this->locale()),
                'content' => $privacy->trans('content', $this->locale()),
                'updated_at' => $privacy->updated_at->format('d M Y'),
            ],
        ]);
    }

    public function getWeddingDecorationPolicy(): JsonResponse
    {
        $policy = WeddingDecorationPolicy::first();

        if (! $policy) {
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => null,
                    'title' => 'Wedding Decoration Policy',
                    'content' => null,
                    'updated_at' => null,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $policy->id,
                'title' => $policy->trans('title', $this->locale()),
                'content' => $policy->trans('content', $this->locale()),
                'updated_at' => $policy->updated_at->format('d M Y'),
            ],
        ]);
    }

    public function getAbout(): JsonResponse
    {
        $about = LegalPage::where('slug', 'about')->first();

        if (! $about) {
            return response()->json([
                'success' => true,
                'data' => [
                    'title' => 'About WeddingApp',
                    'content' => 'Wedding Flowers Organizer is your ultimate companion.',
                    'mission' => null,
                    'owner' => config('app.name'),
                ],
            ]);
        }

        $aboutTitle = $about->trans('title', $this->locale());
        $aboutContent = $about->trans('content', $this->locale());

        return response()->json([
            'success' => true,
            'data' => [
                'title' => $aboutTitle ?? 'About WeddingApp',
                'content' => is_array($aboutContent) ? ($aboutContent['text'] ?? $aboutContent) : $aboutContent,
                'mission' => is_array($aboutContent) ? ($aboutContent['mission'] ?? null) : null,
                'owner' => config('app.name'),
            ],
        ]);
    }

    public function getHelp(): JsonResponse
    {
        $help = Help::first();

        return response()->json([
            'success' => true,
            'data' => [
                'title' => $help?->trans('title', $this->locale()) ?? 'Help Center',
                'subtitle' => $help?->trans('subtitle', $this->locale()) ?? 'We are here to help you.',
                'faqs' => $help?->trans('faqs', $this->locale()) ?? [],
                'contact_options' => $help?->contact_options ?? null,
            ],
        ]);
    }
}
