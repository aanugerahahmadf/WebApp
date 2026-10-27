<?php

namespace App\Services\AiConversationService;

use App\Models\Inbox\Inbox;
use App\Models\Message\Message;
use App\Support\ChatStaff\ChatStaff;
use Illuminate\Support\Str;

/**
 * One small, provider-agnostic conversation gateway for web and mobile chat.
 *
 * The inbox is the source of truth, therefore guests and authenticated users
 * get exactly the same context once they are attached to an inbox. The model
 * is optional: when no compatible endpoint is configured or reachable, the
 * deterministic fallback still answers in the language of the last message.
 */
class AiConversationService
{
    public function reply(Message $message): string
    {
        $inbox = $message->inbox;
        $language = $this->detectLanguage((string) $message->message);
        $history = $this->history($inbox, $message);
        // This project uses the built-in conversational bot. It deliberately
        // does not call OpenAI/Ollama or any external service: guest chat must
        // remain fast and functional even when the internet or AI Core is down.
        return $this->fallback($message, $history, $language);
    }

    public function detectLanguage(string $text): string
    {
        $text = Str::lower($text);
        if ($text === '') {
            return 'id';
        }

        $indonesian = preg_match_all('/\b(aku|saya|kami|kita|anda|kamu|yang|dan|dengan|untuk|ingin|bisa|tolong|berapa|harga|pesan|bunga|pernikahan|terima|kasih|dimana|apakah|ini|itu|tidak|nggak|mau)\b/u', $text);
        $english = preg_match_all('/\b(i|we|you|the|and|with|for|want|can|please|how|much|price|book|flower|wedding|thank|where|is|this|that|not|need)\b/u', $text);

        return $english > $indonesian ? 'en' : 'id';
    }

    /** @return list<array{role:string,content:string}> */
    private function history(?Inbox $inbox, Message $current): array
    {
        if (! $inbox) {
            return [['role' => 'user', 'content' => (string) $current->message]];
        }

        return $inbox->messages()
            ->where('id', '!=', $current->getKey())
            ->whereNotNull('message')
            ->latest('id')
            ->limit((int) config('services.ai_chat_context_messages', 16))
            ->get(['user_id', 'message', 'meta'])
            ->reverse()
            ->map(function (Message $item): array {
                return [
                    'role' => ! empty(($item->meta ?? [])['is_bot']) ? 'assistant' : 'user',
                    'content' => Str::limit(trim((string) $item->message), 1200, '…'),
                ];
            })->values()->all();
    }

    private function fallback(Message $message, array $history, string $language): string
    {
        $text = trim((string) $message->message);
        $lower = Str::lower($text);
        $previous = collect($history)->last(fn (array $item) => $item['role'] === 'user')['content'] ?? null;
        $context = $previous ? ($language === 'en'
            ? " I remember you were also asking about \"{$previous}\"."
            : " Saya ingat sebelumnya Anda juga menanyakan \"{$previous}\".") : '';

        if (preg_match('/\b(hi|hello|hey|good morning|good afternoon|good evening)\b/i', $text)) {
            return "Hello!{$context} How can I help with your wedding flower decoration today?";
        }
        if (preg_match('/\b(halo|hai|pagi|siang|sore|malam)\b/u', $lower)) {
            return "Halo!{$context} Saya siap membantu dekorasi bunga pernikahan Anda. Apa yang ingin Anda rencanakan hari ini?";
        }
        if (preg_match('/\b(price|cost|budget|how much)\b/i', $text)) {
            return 'I can help compare packages and budget options. Please tell me your event date, venue/city, and estimated guest count; an admin can confirm the final availability and price.';
        }
        if (preg_match('/\b(harga|biaya|budget|berapa)\b/u', $lower)) {
            return 'Saya bisa membantu membandingkan paket dan anggaran. Mohon beri tahu tanggal acara, kota/lokasi, dan perkiraan jumlah tamu; admin akan mengonfirmasi ketersediaan serta harga final.';
        }

        if (preg_match('/\b(order|booking|book|pesan|reserv|beli)\b/i', $text)) {
            return $language === 'en'
                ? 'I can guide your booking. Please share the event date, venue/city, decoration theme, and package you are considering. I will help prepare the next step, while an admin confirms availability.'
                : 'Saya bisa membantu proses pemesanan. Mohon kirim tanggal acara, lokasi, tema dekorasi, dan paket yang sedang dipertimbangkan. Saya bantu langkah berikutnya, lalu admin mengonfirmasi ketersediaannya.';
        }
        if (preg_match('/\b(bunga|flower|rose|mawar|warna|color|theme|tema)\b/iu', $text)) {
            return $language === 'en'
                ? 'Beautiful choice! Tell me your preferred flower or color, wedding style, and venue. I can help narrow the decoration concept to match your atmosphere and budget.'
                : 'Pilihan yang indah! Ceritakan bunga atau warna yang disukai, gaya pernikahan, dan lokasinya. Saya bantu menyaring konsep dekorasi yang sesuai suasana dan anggaran.';
        }
        if (preg_match('/\b(thank|thanks|terima kasih|makasih)\b/iu', $text)) {
            return $language === 'en'
                ? 'You are welcome! I am here to help with the flowers, package, budget, or booking whenever you are ready.'
                : 'Sama-sama! Saya siap membantu bunga, paket, anggaran, atau pemesanan kapan pun Anda siap.';
        }

        return $language === 'en'
            ? "Thanks for explaining that.{$context} I can help with flowers, packages, budget, event details, orders, and payments. Please share the specific detail you need, and I will guide you step by step."
            : "Terima kasih sudah menjelaskannya.{$context} Saya bisa membantu bunga, paket, anggaran, detail acara, pesanan, dan pembayaran. Silakan tuliskan detail yang dibutuhkan agar saya memandu Anda langkah demi langkah.";
    }
}
