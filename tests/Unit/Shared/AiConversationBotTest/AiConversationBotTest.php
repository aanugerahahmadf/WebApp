<?php

use App\Models\Inbox\Inbox;
use App\Models\Message\Message;
use App\Models\User\User;
use App\Services\AiConversationService\AiConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('detects Indonesian and English from the actual message instead of UI locale', function (): void {
    $bot = app(AiConversationService::class);

    expect($bot->detectLanguage('Berapa harga paket bunga untuk pernikahan saya?'))->toBe('id')
        ->and($bot->detectLanguage('How much is the flower package for my wedding?'))->toBe('en');
});

it('keeps guest and user context in the same inbox and answers in Indonesian', function (): void {
    $guest = User::factory()->create(['email' => 'guest_context@guest.local']);
    $inbox = Inbox::create(['user_ids' => [$guest->id, $guest->id + 1]]);
    Message::create([
        'inbox_id' => $inbox->id,
        'user_id' => $guest->id,
        'message' => 'Saya ingin dekorasi warna putih',
    ]);
    $current = Message::create([
        'inbox_id' => $inbox->id,
        'user_id' => $guest->id,
        'message' => 'Berapa harga paketnya?',
    ]);

    $reply = app(AiConversationService::class)->reply($current);

    expect($reply)->toContain('anggaran')
        ->and($reply)->toContain('tanggal acara');
});

it('answers English without translating it into a canned Indonesian response', function (): void {
    $user = User::factory()->create();
    $inbox = Inbox::create(['user_ids' => [$user->id, $user->id + 1]]);
    $current = Message::create([
        'inbox_id' => $inbox->id,
        'user_id' => $user->id,
        'message' => 'Hello, I need help choosing flowers for my wedding.',
    ]);

    $reply = app(AiConversationService::class)->reply($current);

    expect($reply)->toContain('Hello')
        ->and($reply)->toContain('wedding flower decoration');
});
