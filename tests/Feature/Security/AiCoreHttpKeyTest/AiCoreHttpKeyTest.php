<?php

use Illuminate\Support\Facades\Http;

/*
 * Kontrak transport Laravel -> AI Core.
 *
 * AI Core menolak request non-health dengan 401 begitu AI_CORE_API_KEY diisi.
 * Jalur gRPC sudah mengirim key lewat metadata, tetapi jalur HTTP (CBIR,
 * face, KTP, proof, cbir:check, ai:sync, panel, GraphQL) dulu tidak. Test ini
 * mengunci perilaku middleware global supaya key tidak hilang lagi saat ada
 * pemanggil HTTP baru.
 */

beforeEach(function (): void {
    Http::preventStrayRequests();
});

test('HTTP request ke origin AI Core membawa X-AI-Core-Key', function (): void {
    config([
        'services.ai_core_url' => 'http://127.0.0.1:5000/',
        'services.ai_core_api_key' => 'test-ai-core-key',
    ]);

    Http::fake([
        'http://127.0.0.1:5000/*' => Http::response(['status' => 'ok']),
    ]);

    Http::get('http://127.0.0.1:5000/status');

    Http::assertSent(fn ($request): bool => $request->url() === 'http://127.0.0.1:5000/status'
        && $request->hasHeader('X-AI-Core-Key', 'test-ai-core-key'));
});

test('key tidak dikirim ke host non-AI Core', function (): void {
    config([
        'services.ai_core_url' => 'http://127.0.0.1:5000',
        'services.ai_core_api_key' => 'test-ai-core-key',
    ]);

    Http::fake([
        'https://example.test/*' => Http::response(['ok' => true]),
    ]);

    Http::post('https://example.test/webhook', ['hello' => 'world']);

    Http::assertSent(fn ($request): bool => $request->url() === 'https://example.test/webhook'
        && ! $request->hasHeader('X-AI-Core-Key'));
});

test('tanpa AI_CORE_API_KEY tidak ada header yang dikirim', function (): void {
    config([
        'services.ai_core_url' => 'http://127.0.0.1:5000',
        'services.ai_core_api_key' => null,
    ]);

    Http::fake([
        'http://127.0.0.1:5000/*' => Http::response(['status' => 'ok']),
    ]);

    Http::get('http://127.0.0.1:5000/health');

    Http::assertSent(fn ($request): bool => ! $request->hasHeader('X-AI-Core-Key'));
});
