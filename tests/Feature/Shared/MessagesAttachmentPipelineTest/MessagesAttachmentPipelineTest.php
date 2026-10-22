<?php

/**
 * Jalur lampiran pesan: kamera (foto/video), audio, dan kontak.
 *
 * Semua modality itu berakhir di hal yang SAMA: browser membuat File,
 * menyerahkannya ke komponen Livewire lewat `$wire.upload()`, lalu
 * komponen memanggil `sendPendingAttachment($tmpFilename)`.
 *
 * Test ini mengunci bagian terakhirnya -- file yang benar-benar sampai ke
 * media library dan menempel ke baris pesan. Bagian ini yang pernah hilang:
 * tombol "Kirim Audio" tampak berhasil, tapi tidak ada satu baris pun di
 * tabel `media`, jadi gelembung pesannya tidak pernah muncul.
 *
 * Yang diuji di sini:
 *   1. `pendingAttachment` (TemporaryUploadedFile) diteruskan ke
 *      `sendPendingAttachment()`.
 *   2. Filenya di-store ke disk `public` dan disalin ke media library
 *      dengan koleksi FILAMENT_MESSAGES.
 *   3. Baris pesannya benar-benar ada, dan lampirannya menempel padanya.
 */

use App\Enums\Messages\MediaCollectionType\MediaCollectionType;
use App\Jobs\SendBotReply\SendBotReply;
use App\Livewire\Admin\Messages\Messages\Messages as AdminMessages;
use App\Livewire\User\Messages\Messages\Messages as UserMessages;
use App\Livewire\Welcome\Messages\Messages\Messages as WelcomeMessages;
use App\Models\Admin\Admin;
use App\Models\Inbox\Inbox as InboxModel;
use App\Models\Message\Message;
use App\Models\User\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * WAV kosong (44 byte header, tanpa sample data).
 *
 * Cukup buat `finfo` mengenali filenya sebagai audio -- yang dibutuhkan test
 * ini cuma mime type-nya, bukan suara.
 */
function wavBytes(): string
{
    return 'RIFF'
        . pack('V', 36)
        . 'WAVE'
        . 'fmt '
        . pack('V', 16)
        . pack('v', 1)          // PCM
        . pack('v', 1)          // mono
        . pack('V', 8000)       // sample rate
        . pack('V', 8000)       // byte rate
        . pack('v', 1)          // block align
        . pack('v', 8)          // bits per sample
        . 'data'
        . pack('V', 0);         // 0 byte sample
}

/**
 * Tiga komponen percakapan (Welcome, User, Admin) punya salinan kode yang
 * sama untuk jalur lampiran. Yang diuji di sini adalah KETIGANYA, karena
 * salinan bisa menyimpang -- dan tidak ada yang mengetahuinya sampai satu
 * panel melambat.
 */
dataset('messagesPanels', [
    'welcome' => [WelcomeMessages::class, 'welcome'],
    'user' => [UserMessages::class, 'user'],
    'admin' => [AdminMessages::class, 'admin'],
]);

beforeEach(function (): void {
    Storage::fake('public');

    seedRoles(adminRoles: ['super_admin'], webRoles: ['super_admin', 'customer']);

    // User BELANJA -- ini yang masuk ke `inboxes.user_ids` dan
    // `messages.user_id`.
    $this->admin = User::factory()->create();
    $this->admin->assignRole('super_admin');

    // `ChatStaff::adminUser()` mencari baris `admins` yang tertaut ke user di
    // atas. Tanpa tautan itu, panel admin tidak punya identitas staf dan
    // `chatUserId()` mengembalikan null.
    Admin::factory()->superAdmin()->create([
        'active_status' => true,
        'user_id'       => $this->admin->id,
    ]);

    $this->customer = User::factory()->create();
    $this->customer->assignRole('customer');

    $this->inbox = InboxModel::create([
        'user_ids' => [$this->customer->id, $this->admin->id],
    ]);

    Queue::fake();
});

it('attaches an uploaded voice note to the message as media', function (string $componentClass, string $panelId): void {
    Filament::setCurrentPanel(Filament::getPanel($panelId));

    // Panel admin bicara sebagai staf, panel lain sebagai pembeli.
    actingAs($panelId === 'admin' ? $this->admin : $this->customer, 'web');

    $component = Livewire::test($componentClass, ['selectedConversation' => $this->inbox]);

    /*
     * Isi file harus byte WAV yang sungguhan, bukan file kosong.
     *
     * Media library menebak mime type dari ISI file, bukan dari nama atau
     * dari tipe yang diklaim browser. `UploadedFile::fake()->create()` membuat
     * file KOSONG, jadi tipenya jadi 'application/x-empty' -- bukan
     * 'audio/webm' seperti yang diklaim. File kosong juga tidak akan
     * pernah diputar sebagai audio di gelembung pesan.
     */
    $component->set('pendingAttachment', UploadedFile::fake()->createWithContent('voice-note.wav', wavBytes()));

    $tmpFilename = $component->get('pendingAttachment')->getFilename();

    expect($tmpFilename)->not->toBeEmpty();

    $component->call('sendPendingAttachment', $tmpFilename);

    $message = Message::query()->latest('id')->first();

    expect($message)->not->toBeNull()
        ->and($message->user_id)->toBe($panelId === 'admin' ? $this->admin->id : $this->customer->id);

    $media = $message->getMedia(MediaCollectionType::FILAMENT_MESSAGES->value);

    expect($media)->toHaveCount(1)
        ->and($media->first()->mime_type)->toStartWith('audio/');

    // The file really is on disk, in the media library, not just a row.
    expect($media->first()->getPath())->toBeFile();
})->with('messagesPanels');

it('attaches an uploaded photo to the message as media', function (string $componentClass, string $panelId): void {
    Filament::setCurrentPanel(Filament::getPanel($panelId));

    actingAs($panelId === 'admin' ? $this->admin : $this->customer, 'web');

    $component = Livewire::test($componentClass, ['selectedConversation' => $this->inbox]);

    $component->set('pendingAttachment', UploadedFile::fake()->image('camera-photo.jpg'));

    $component->call('sendPendingAttachment', $component->get('pendingAttachment')->getFilename());

    $message = Message::query()->latest('id')->first();

    $media = $message->getMedia(MediaCollectionType::FILAMENT_MESSAGES->value);

    expect($media)->toHaveCount(1)
        ->and($media->first()->mime_type)->toStartWith('image/');
})->with('messagesPanels');

it('attaches an uploaded contact card to the message as media', function (string $componentClass, string $panelId): void {
    Filament::setCurrentPanel(Filament::getPanel($panelId));

    actingAs($panelId === 'admin' ? $this->admin : $this->customer, 'web');

    $component = Livewire::test($componentClass, ['selectedConversation' => $this->inbox]);

    // Isi vCard yang sungguhan, supaya mime type-nya terdeteksi sebagai teks.
    $vcard = "BEGIN:VCARD\nVERSION:3.0\nFN:Jakarta Wedding\nTEL:+628123456789\nEND:VCARD\n";

    $component->set('pendingAttachment', UploadedFile::fake()->createWithContent('kontak.vcf', $vcard));

    $component->call('sendPendingAttachment', $component->get('pendingAttachment')->getFilename());

    $message = Message::query()->latest('id')->first();

    $media = $message->getMedia(MediaCollectionType::FILAMENT_MESSAGES->value);

    expect($media)->toHaveCount(1)
        ->and($media->first()->mime_type)->toStartWith('text/');
})->with('messagesPanels');

it('sends the bot reply even when the message only carries an attachment', function (string $componentClass, string $panelId): void {
    Filament::setCurrentPanel(Filament::getPanel($panelId));

    actingAs($panelId === 'admin' ? $this->admin : $this->customer, 'web');

    $component = Livewire::test($componentClass, ['selectedConversation' => $this->inbox]);

    $component->set('pendingAttachment', UploadedFile::fake()->create('voice-note.webm', 24, 'audio/webm'));

    $component->call('sendPendingAttachment', $component->get('pendingAttachment')->getFilename());

    // Panel admin bicara sebagai staf, jadi bot tidak menyapa staf.
    if ($panelId === 'admin') {
        Queue::assertNotPushed(SendBotReply::class);

        return;
    }

    Queue::assertPushed(SendBotReply::class);
})->with('messagesPanels');
