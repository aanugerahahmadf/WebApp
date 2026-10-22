<?php

namespace App\Livewire\Shared\Traits\CanSendRichMessages;

use App\Jobs\SendBotReply\SendBotReply;
use App\Support\ChatStaff\ChatStaff;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

/**
 * Pesan "@" beyond teks biasa: polling dan stiker.
 *
 * Dipakai tiga komponen percakapan (Admin, User, Welcome) dari satu file,
 * supaya format data poll dan aturan voting-nya tidak bisa berbeda antar
 * panel.
 *
 * POLL disimpan di `meta` pesan:
 *
 *   meta['poll']   = ['question' => '...', 'options' => [...]]
 *   meta['poll']   = ['question' => '...', 'options' => [['text' => '...', 'votes' => [id, ...]], ...]]
 *
 * `votes` berisi id pengguna yang memilih opsi itu. Satu pengguna hanya boleh
 * memilih satu opsi: memilih ulang memindahkan suaranya ke opsi yang baru.
 *
 * STIKER disimpan sebagai:
 *
 *   meta['sticker']= '😀'
 *
 * Teks pesan tetap berisi karakternya, jadi stiker tetap terbaca sebagai teks
 * polos di notifikasi, ringkasan, dan pencarian.
 */
trait CanSendRichMessages
{
    /**
     * Id pengguna yang sedang memakai komponen ini.
     *
     * Panel Admin dan Welcome memakai identitas chatting (bisa tamu, bukan
     * hanya user yang login), sedangkan panel User memakai user yang login.
     */
    public function chatParticipantId(): ?int
    {
        if (method_exists($this, 'chatUserId')) {
            $id = $this->chatUserId();

            if ($id !== null) {
                return (int) $id;
            }
        }

        return Auth::id() !== null ? (int) Auth::id() : null;
    }

    /**
     * Buat polling baru di percakapan aktif.
     *
     * @param  array<int, string>  $options
     */
    public function createPoll(string $question, array $options): void
    {
        $question = trim($question);

        $cleanOptions = collect($options)
            ->map(fn ($option): string => trim((string) $option))
            ->filter(fn (string $option): bool => $option !== '')
            ->unique()
            ->values()
            ->take(10)
            ->all();

        if ($question === '' || count($cleanOptions) < 2) {
            Notification::make()
                ->title(__('Poll belum lengkap'))
                ->body(__('Isi pertanyaannya dan pilih minimal dua pilihan.'))
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $this->createRichMessage($question, [
            // TIDAK memakai kunci 'type': kunci itu sudah dipakai view
            // percakapan untuk menandai pesan yang merujuk katalog/paket.
            'poll' => [
                'question' => $question,
                'options' => array_map(
                    fn (string $option): array => ['text' => $option, 'votes' => []],
                    $cleanOptions,
                ),
            ],
        ]);
    }

    /**
     * Pilih satu opsi pada sebuah poll.
     *
     * Memilih ulang opsi yang sama membatalkan suara; memilih opsi lain
     * memindahkan suara ke opsi itu.
     */
    public function votePoll(int $messageId, int $optionIndex): void
    {
        $message = $this->selectedConversation?->messages()->find($messageId);

        if (! $message) {
            return;
        }

        $meta = is_array($message->meta) ? $message->meta : [];
        $poll = $meta['poll'] ?? null;

        if (! is_array($poll) || ! isset($poll['options'][$optionIndex])) {
            return;
        }

        $userId = $this->chatParticipantId();

        if ($userId === null) {
            return;
        }

        $options = $poll['options'];

        foreach ($options as $index => $option) {
            $votes = is_array($option['votes'] ?? null) ? $option['votes'] : [];
            $votes = array_values(array_filter(
                $votes,
                fn ($vote): bool => (int) $vote !== (int) $userId,
            ));

            if ((int) $index === (int) $optionIndex) {
                $votes[] = (int) $userId;
            }

            $options[$index]['votes'] = $votes;
        }

        $meta['poll']['options'] = $options;

        $message->update(['meta' => $meta]);

        // Segarkan tampilan di percakapan yang sedang terbuka.
        $this->dispatch('refresh-inbox');
    }

    /**
     * Kirim satu stiker.
     */
    public function sendSticker(string $sticker): void
    {
        $sticker = trim($sticker);

        if ($sticker === '') {
            return;
        }

        $this->createRichMessage($sticker, [
            'sticker' => $sticker,
        ]);
    }

    /**
     * Buat satu pesan biasa dengan `meta` tambahan.
     *
     * Alurnya sama dengan sendMessage() supaya balasan bot, penanda terbaca,
     * dan refresh inbox tetap konsisten.
     */
    private function createRichMessage(string $text, array $meta): void
    {
        if (! $this->selectedConversation) {
            return;
        }

        $userId = $this->chatParticipantId();

        if ($userId === null) {
            return;
        }

        $newMessage = $this->selectedConversation->messages()->create([
            'message' => $text,
            'user_id' => $userId,
            'read_by' => [$userId],
            'read_at' => [now()],
            'notified' => [$userId],
            'meta' => $meta,
        ]);

        $chatUser = method_exists($this, 'chatUser') ? $this->chatUser() : Auth::user();

        if (! ChatStaff::isStaff($chatUser)) {
            SendBotReply::dispatch($newMessage->id)->delay(now()->addSeconds(5));
        }

        $this->conversationMessages?->prepend($newMessage);

        $this->dispatch('refresh-inbox');
    }
}
