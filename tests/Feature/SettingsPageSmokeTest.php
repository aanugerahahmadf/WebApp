<?php

use App\Models\Help\Help;
use App\Models\PrivacyPolicy\PrivacyPolicy;
use App\Models\TermsOfService\TermsOfService;
use App\Models\User\User;
use App\Models\WeddingDecorationPolicy\WeddingDecorationPolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders settings page without error', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/user/settings');

    $response->assertStatus(200);

    $html = $response->getContent();

    $this->assertStringContainsString('notification-settings-component', $html, 'notification component not rendered');
    $this->assertStringContainsString('edit-password-component', $html, 'password component not rendered');
    $this->assertStringContainsString('/user/messages', $html, 'Lapor link to messages not found');
});

it('renders help center without error', function () {
    Help::create([
        'title' => 'Pusat Bantuan',
        'subtitle' => 'Subjudul bantuan',
        'faqs' => [
            ['question' => 'Bagaimana cara memesan?', 'answer' => 'Buka katalog lalu klik pesan.'],
        ],
        'contact_options' => [
            ['url' => 'https://wa.me/6281234567890', 'icon' => 'whatsapp', 'label' => 'WhatsApp Support', 'subLabel' => '+62 812-3456-7890'],
            ['url' => 'mailto:support@weddingapp.com', 'icon' => 'mail', 'label' => 'Email Support', 'subLabel' => 'support@weddingapp.com'],
        ],
    ]);

    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/user/help-center');

    $response->assertStatus(200);

    $html = $response->getContent();

    $this->assertStringContainsString('WhatsApp Support', $html, 'contact option label not rendered');
    $this->assertStringContainsString('Bagaimana cara memesan?', $html, 'faq not rendered');
});

it('renders privacy terms hub and linked pages without error', function () {
    PrivacyPolicy::create([
        'title' => 'Kebijakan Privasi',
        'content' => [['heading' => 'Privasi Anda', 'body' => 'Kami menjaga data Anda.']],
    ]);
    TermsOfService::create([
        'title' => 'Ketentuan Layanan',
        'content' => [['heading' => 'Aturan', 'body' => 'Ikuti aturan layanan.']],
    ]);
    WeddingDecorationPolicy::create([
        'title' => 'Kebijakan Aplikasi',
        'content' => [['heading' => 'Dekorasi', 'body' => 'Kebijakan dekorasi pernikahan.']],
    ]);

    $user = User::factory()->create();

    $hub = $this->actingAs($user)->get('/user/privacy-terms');
    $hub->assertStatus(200);
    $hubHtml = $hub->getContent();
    $this->assertStringContainsString('/user/privacy-policy', $hubHtml, 'privacy link not on hub');
    $this->assertStringContainsString('/user/terms-of-service', $hubHtml, 'terms link not on hub');
    $this->assertStringContainsString('/user/wedding-policy', $hubHtml, 'wedding link not on hub');

    $privacy = $this->actingAs($user)->get('/user/privacy-policy');
    $privacy->assertStatus(200);
    $this->assertStringContainsString('Kami menjaga data Anda.', $privacy->getContent(), 'privacy content missing');

    $terms = $this->actingAs($user)->get('/user/terms-of-service');
    $terms->assertStatus(200);
    $this->assertStringContainsString('Ikuti aturan layanan.', $terms->getContent(), 'terms content missing');

    $wedding = $this->actingAs($user)->get('/user/wedding-policy');
    $wedding->assertStatus(200);
    $this->assertStringContainsString('Kebijakan dekorasi pernikahan.', $wedding->getContent(), 'wedding content missing');
});
