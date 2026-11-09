<?php

namespace App\Http\GraphQL\Resolvers;

use App\Models\LegalPage\LegalPage;
use App\Models\TermsOfService\TermsOfService;
use App\Models\PrivacyPolicy\PrivacyPolicy;
use App\Models\WeddingDecorationPolicy\WeddingDecorationPolicy;
use App\Models\Help\Help;
use Nuwave\Lighthouse\Support\Contracts\GraphQLContext;

class LegalResolver
{
    protected function locale(): string
    {
        return app()->getLocale();
    }

    /**
     * Get Terms of Service
     */
    public function termsOfService($root, array $args, GraphQLContext $context)
    {
        $terms = TermsOfService::first();

        if (!$terms) {
            return [
                'id' => null,
                'title' => 'Terms & Conditions',
                'content' => null,
                'updatedAt' => null,
            ];
        }

        return [
            'id' => $terms->id,
            'title' => $terms->trans('title', $this->locale()),
            'content' => $terms->trans('content', $this->locale()),
            'updatedAt' => $terms->updated_at?->format('d M Y'),
        ];
    }

    /**
     * Get Privacy Policy
     */
    public function privacyPolicy($root, array $args, GraphQLContext $context)
    {
        $privacy = PrivacyPolicy::first();

        if (!$privacy) {
            return [
                'id' => null,
                'title' => 'Privacy Policy',
                'content' => null,
                'updatedAt' => null,
            ];
        }

        return [
            'id' => $privacy->id,
            'title' => $privacy->trans('title', $this->locale()),
            'content' => $privacy->trans('content', $this->locale()),
            'updatedAt' => $privacy->updated_at?->format('d M Y'),
        ];
    }

    /**
     * Get Wedding Decoration Policy
     */
    public function weddingDecorationPolicy($root, array $args, GraphQLContext $context)
    {
        $policy = WeddingDecorationPolicy::first();

        if (!$policy) {
            return [
                'id' => null,
                'title' => 'Wedding Decoration Policy',
                'content' => null,
                'updatedAt' => null,
            ];
        }

        return [
            'id' => $policy->id,
            'title' => $policy->trans('title', $this->locale()),
            'content' => $policy->trans('content', $this->locale()),
            'updatedAt' => $policy->updated_at?->format('d M Y'),
        ];
    }

    /**
     * Get legal page by slug
     */
    public function legalPage($root, array $args, GraphQLContext $context)
    {
        $page = LegalPage::where('slug', $args['slug'])->first();

        if (!$page) {
            return null;
        }

        return [
            'id' => $page->id,
            'slug' => $page->slug,
            'title' => $page->trans('title', $this->locale()),
            'content' => $page->trans('content', $this->locale()),
            'updatedAt' => $page->updated_at?->format('d M Y'),
        ];
    }

    /**
     * Get all legal pages
     */
    public function legalPages($root, array $args, GraphQLContext $context)
    {
        $pages = LegalPage::orderBy('slug')->get();

        return $pages->map(function ($page) {
            return [
                'id' => $page->id,
                'slug' => $page->slug,
                'title' => $page->trans('title', $this->locale()),
                'content' => $page->trans('content', $this->locale()),
                'updatedAt' => $page->updated_at?->format('d M Y'),
            ];
        });
    }

    /**
     * Get About page
     */
    public function about($root, array $args, GraphQLContext $context)
    {
        $about = LegalPage::where('slug', 'about')->first();

        if (!$about) {
            return [
                'title' => 'About WeddingApp',
                'content' => 'Wedding Flower Decorations is your ultimate companion.',
                'mission' => null,
                'owner' => config('app.name'),
            ];
        }

        $aboutTitle = $about->trans('title', $this->locale());
        $aboutContent = $about->trans('content', $this->locale());

        return [
            'title' => $aboutTitle ?? 'About WeddingApp',
            'content' => is_array($aboutContent) ? ($aboutContent['text'] ?? $aboutContent) : $aboutContent,
            'mission' => is_array($aboutContent) ? ($aboutContent['mission'] ?? null) : null,
            'owner' => config('app.name'),
        ];
    }

    /**
     * Get Help Center
     */
    public function helpCenter($root, array $args, GraphQLContext $context)
    {
        $help = Help::first();

        return [
            'title' => $help?->trans('title', $this->locale()) ?? 'Help Center',
            'subtitle' => $help?->trans('subtitle', $this->locale()) ?? 'We are here to help you.',
            'faqs' => $help?->trans('faqs', $this->locale()) ?? [],
            'contactOptions' => $help?->contact_options ?? null,
        ];
    }
}
