<?php

namespace App\Grpc\Services;

use App\Models\LegalPage\LegalPage;
use App\Models\TermsOfService\TermsOfService;
use App\Models\PrivacyPolicy\PrivacyPolicy;
use App\Models\WeddingDecorationPolicy\WeddingDecorationPolicy;
use App\Models\Help\Help;
use Grpc\ServerCall;

class LegalService
{
    protected function locale(): string
    {
        return app()->getLocale();
    }

    public function GetTermsOfService(ServerCall $call, $request)
    {
        $terms = TermsOfService::first();

        if (!$terms) {
            $proto = new \Weddingapp\Legal\LegalContent();
            $proto->setTitle('Terms & Conditions');
            return $proto;
        }

        $proto = new \Weddingapp\Legal\LegalContent();
        $proto->setId($terms->id);
        $proto->setTitle($terms->trans('title', $this->locale()) ?? '');
        $proto->setContent(is_array($terms->trans('content', $this->locale())) 
            ? json_encode($terms->trans('content', $this->locale())) 
            : ($terms->trans('content', $this->locale()) ?? ''));
        $proto->setUpdatedAt($terms->updated_at?->format('d M Y') ?? '');

        return $proto;
    }

    public function GetPrivacyPolicy(ServerCall $call, $request)
    {
        $privacy = PrivacyPolicy::first();

        if (!$privacy) {
            $proto = new \Weddingapp\Legal\LegalContent();
            $proto->setTitle('Privacy Policy');
            return $proto;
        }

        $proto = new \Weddingapp\Legal\LegalContent();
        $proto->setId($privacy->id);
        $proto->setTitle($privacy->trans('title', $this->locale()) ?? '');
        $proto->setContent(is_array($privacy->trans('content', $this->locale())) 
            ? json_encode($privacy->trans('content', $this->locale())) 
            : ($privacy->trans('content', $this->locale()) ?? ''));
        $proto->setUpdatedAt($privacy->updated_at?->format('d M Y') ?? '');

        return $proto;
    }

    public function GetWeddingDecorationPolicy(ServerCall $call, $request)
    {
        $policy = WeddingDecorationPolicy::first();

        if (!$policy) {
            $proto = new \Weddingapp\Legal\LegalContent();
            $proto->setTitle('Wedding Decoration Policy');
            return $proto;
        }

        $proto = new \Weddingapp\Legal\LegalContent();
        $proto->setId($policy->id);
        $proto->setTitle($policy->trans('title', $this->locale()) ?? '');
        $proto->setContent(is_array($policy->trans('content', $this->locale())) 
            ? json_encode($policy->trans('content', $this->locale())) 
            : ($policy->trans('content', $this->locale()) ?? ''));
        $proto->setUpdatedAt($policy->updated_at?->format('d M Y') ?? '');

        return $proto;
    }

    public function GetLegalPage(ServerCall $call, $request)
    {
        $slug = $request->getSlug();
        $page = LegalPage::where('slug', $slug)->first();

        if (!$page) {
            throw new \Grpc\RpcException(\Grpc\STATUS_NOT_FOUND, "Legal page not found");
        }

        $proto = new \Weddingapp\Legal\LegalPage();
        $proto->setId($page->id);
        $proto->setSlug($page->slug);
        $proto->setTitle($page->trans('title', $this->locale()) ?? '');
        $proto->setContent(is_array($page->trans('content', $this->locale())) 
            ? json_encode($page->trans('content', $this->locale())) 
            : ($page->trans('content', $this->locale()) ?? ''));
        $proto->setUpdatedAt($page->updated_at?->format('d M Y') ?? '');

        return $proto;
    }

    public function GetLegalPages(ServerCall $call, $request)
    {
        $pages = LegalPage::orderBy('slug')->get();

        $response = new \Weddingapp\Legal\LegalPagesResponse();
        $pageProtos = [];

        foreach ($pages as $page) {
            $proto = new \Weddingapp\Legal\LegalPage();
            $proto->setId($page->id);
            $proto->setSlug($page->slug);
            $proto->setTitle($page->trans('title', $this->locale()) ?? '');
            $proto->setContent(is_array($page->trans('content', $this->locale())) 
                ? json_encode($page->trans('content', $this->locale())) 
                : ($page->trans('content', $this->locale()) ?? ''));
            $proto->setUpdatedAt($page->updated_at?->format('d M Y') ?? '');
            $pageProtos[] = $proto;
        }

        $response->setPagesValue($pageProtos);
        return $response;
    }

    public function GetAbout(ServerCall $call, $request)
    {
        $about = LegalPage::where('slug', 'about')->first();

        $proto = new \Weddingapp\Legal\AboutPage();

        if (!$about) {
            $proto->setTitle('About Timeless Florals');
            $proto->setContent('Timeless Florals is your ultimate companion.');
            $proto->setOwner(config('app.name'));
            return $proto;
        }

        $aboutTitle = $about->trans('title', $this->locale());
        $aboutContent = $about->trans('content', $this->locale());

        $proto->setTitle($aboutTitle ?? 'About Timeless Florals');
        $proto->setContent(is_array($aboutContent) ? ($aboutContent['text'] ?? $aboutContent) : $aboutContent);
        $proto->setMission(is_array($aboutContent) ? ($aboutContent['mission'] ?? '') : '');
        $proto->setOwner(config('app.name'));

        return $proto;
    }

    public function GetHelpCenter(ServerCall $call, $request)
    {
        $help = Help::first();

        $proto = new \Weddingapp\Legal\HelpCenter();
        $proto->setTitle($help?->trans('title', $this->locale()) ?? 'Help Center');
        $proto->setSubtitle($help?->trans('subtitle', $this->locale()) ?? 'We are here to help you.');

        $faqs = $help?->trans('faqs', $this->locale()) ?? [];
        $faqProtos = [];
        foreach ($faqs as $faq) {
            $faqProto = new \Weddingapp\Legal\FaqItem();
            $faqProto->setQuestion($faq['question'] ?? $faq['q'] ?? '');
            $faqProto->setAnswer($faq['answer'] ?? $faq['a'] ?? '');
            $faqProtos[] = $faqProto;
        }
        $proto->setFaqsValue($faqProtos);

        $contactOptions = $help?->contact_options ?? null;
        if ($contactOptions) {
            $proto->setContactOptions(is_array($contactOptions) ? json_encode($contactOptions) : $contactOptions);
        }

        return $proto;
    }
}
