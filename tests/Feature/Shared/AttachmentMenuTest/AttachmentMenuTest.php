<?php

/**
 * Menu lampiran "+" di composer percakapan.
 *
 * Menu ini hidup di scope Alpine sendiri (`attachOpen`). Scope Alpine
 * digabung dengan scope nenek moya, jadi nama variabel yang BENAR akan
 * tertimpa oleh scope yang lebih dekat -- dan di halaman ini ada `open`
 * milik komponen global `messageBubble` (gelembung aksi pesan).
 *
 * Gejalanya kalau salah nama: menu "+" TIDAK menutup saat item diklik,
 * karena yang ketutup bukan `attachOpen` tapi `open` milik gelembung pesan.
 * Tidak ada error, tidak ada stack trace -- cuma menu yang tetap nempel di
 * atas modal yang baru dibuka.
 */

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('public');
});
/**
 * Isi file blade menu lampiran.
 */
function attachmentMenuSource(): string
{
    return (string) file_get_contents(
        resource_path('views/Shared/components/attachment-menu/attachment-menu.blade.php')
    );
}

it('closes the menu with attachOpen, never with the bare open variable', function (): void {
    $source = attachmentMenuSource();

    // Semua ekspresi Alpine di item menu diambil dari key 'click'.
    preg_match_all("/'click'\s*=>\s*\"(.*?)\"/s", $source, $matches);

    expect($matches[1])->not->toBeEmpty('menu lampiran tidak punya item sama sekali');

    foreach ($matches[1] as $expression) {
        // `attachOpen = false` boleh. `open = false` TIDAK boleh: itu
        // variabel milik messageBubble.
        expect($expression)
            ->not->toMatch('/(?<![\w])open\s*=/', "ekspresi memakai `open` milik messageBubble: {$expression}");
    }
});

it('keeps every attachment action wired to its own event', function (): void {
    $source = attachmentMenuSource();

    foreach ([
        'cbir-browse-open',       // Document
        'cbir-browse-pick-image', // Photos & videos
        'messages-camera-open',   // Camera Photo / Camera Video
        'messages-audio-open',    // Audio
        'messages-contact-open',  // Contact
        'messages-poll-open',     // Poll
        'messages-sticker-open',  // New sticker
    ] as $event) {
        expect($source)->toContain($event);
    }
});

it('opens the camera modal in the mode each camera item asks for', function (): void {
    $source = attachmentMenuSource();

    // Dua item kamera: satu mode foto, satu mode video. Kalau keduanya
    // membuka mode yang sama, tombol "Camera Video" jadi salinan tombol
    // "Camera Photo" -- persis bug yang sudah pernah terjadi.
    expect($source)->toContain("messages-camera-open', { detail: { mode: 'photo' } }")
        ->and($source)->toContain("messages-camera-open', { detail: { mode: 'video' } }");
});

it('sends the poll and the sticker through their own window events', function (): void {
    $source = attachmentMenuSource();

    expect($source)->toContain("new CustomEvent('messages-poll-open')")
        ->and($source)->toContain("new CustomEvent('messages-sticker-open')");
});
