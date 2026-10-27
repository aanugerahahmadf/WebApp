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

        $indonesian = preg_match_all('/\b(aku|saya|kami|kita|anda|kamu|hallo|yang|dan|dengan|untuk|ingin|bisa|tolong|berapa|harga|pesan|bunga|pernikahan|terima|kasih|dimana|apakah|ini|itu|tidak|nggak|mau)\b/u', $text);
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
        $previousUserText = collect($history)
            ->reverse()
            ->first(fn (array $item) => $item['role'] === 'user')['content'] ?? '';
        $combined = Str::lower($previousUserText.' '.$text);
        $context = $previousUserText !== '' ? ($language === 'en'
            ? ' I am keeping your earlier details in mind.'
            : ' Saya tetap mengingat detail yang Anda sampaikan sebelumnya.') : '';

        // Short confirmations are follow-ups, not new conversations. Infer the
        // topic from the previous turn so "iya" / "yes" stays connected.
        if ($this->matches($lower, '^(iya|ya|yes|oke|ok|baik|lanjut|sure|please do|boleh)$')) {
            if ($this->matches($combined, 'harga|price|budget|biaya')) {
                return $language === 'en'
                    ? 'Great. Please send the event date, city or venue, estimated guests, and the package or decoration style you like. The admin will then confirm the exact quote.'
                    : 'Baik. Silakan kirim tanggal acara, kota atau lokasi, perkiraan jumlah tamu, serta paket atau gaya dekorasi yang disukai. Admin kemudian mengonfirmasi penawaran tepatnya.';
            }
            if ($this->matches($combined, 'pesan|booking|book|order|reserv')) {
                return $language === 'en'
                    ? 'Perfect. The booking steps are: choose a catalogue or package, confirm the date and concept, review the quotation, then pay the required deposit. An admin will guide the confirmation.'
                    : 'Baik. Langkah pemesanan adalah memilih katalog atau paket, mengonfirmasi tanggal dan konsep, meninjau penawaran, lalu membayar uang muka yang diperlukan. Admin akan membantu konfirmasinya.';
            }
        }

        if ($this->matches($lower, '^(hello|hallo|hi|hey)\b')) {
            return $language === 'en'
                ? "Hello!{$context} I am your wedding flower decoration assistant. How are you today? Tell me what you are planning, and I will help step by step."
                : "Halo!{$context} Saya BOT bantuan dekorasi pernikahan. Apa kabar? Ceritakan rencana atau kebutuhan Anda, nanti saya bantu langkah demi langkah.";
        }
        if ($this->matches($lower, '^(halo|hallo|hai|selamat pagi|selamat siang|selamat sore|selamat malam)[!,. ]*$')) {
            return "Halo!{$context} Saya BOT bantuan dekorasi pernikahan. Apa kabar? Ceritakan rencana atau kebutuhan Anda, nanti saya bantu langkah demi langkah.";
        }
        if ($this->matches($lower, 'thank|thanks|thank you|terima kasih|makasih|tq')) {
            return $language === 'en'
                ? 'You are welcome! I will stay with you if you want to continue with flowers, packages, budget, booking, or your order.'
                : 'Sama-sama! Saya siap melanjutkan jika Anda ingin membahas bunga, paket, anggaran, pemesanan, atau pesanan Anda.';
        }
        if ($this->matches($combined, 'urgent|darurat|urgent|komplain|complaint|kecewa|disappointed|masalah serius|serious problem')) {
            return $language === 'en'
                ? 'I am sorry this is difficult. I have marked the conversation as important. Please share your order number and the exact problem; an admin will verify it directly. Do not send passwords or payment PINs here.'
                : 'Maaf masalah ini membuat Anda tidak nyaman. Percakapan ini saya tandai penting. Silakan kirim nomor pesanan dan kendala tepatnya; admin akan memeriksanya langsung. Jangan kirim kata sandi atau PIN pembayaran di sini.';
        }
        if ($this->matches($combined, 'admin|human|person|staff|manusia|orang|cs|customer service|petugas')) {
            return $language === 'en'
                ? 'Of course. I will keep the conversation context ready for our admin. Please write the topic and any order number, and the admin can continue without you repeating everything.'
                : 'Tentu. Saya akan menjaga konteks percakapan ini agar admin dapat melanjutkannya. Tuliskan topik dan nomor pesanan jika ada, sehingga Anda tidak perlu mengulang semuanya.';
        }
        if ($this->matches($combined, 'login|sign in|password|account|akun|masuk|daftar|register|verification|verifikasi')) {
            return $language === 'en'
                ? 'For account help, tell me whether the issue is signing in, a forgotten password, verification, or profile data. Never share your password or OTP; an admin will help through the secure account flow.'
                : 'Untuk bantuan akun, jelaskan apakah kendalanya login, lupa kata sandi, verifikasi, atau data profil. Jangan pernah membagikan kata sandi atau OTP; admin akan membantu melalui alur akun yang aman.';
        }
        if ($this->matches($combined, 'payment|pay|transfer|qris|virtual account|va|pembayaran|bayar|transfer')) {
            return $language === 'en'
                ? 'For payment help, please provide the order number, payment method, and the status shown in the app. You may attach proof of transfer, but hide sensitive account numbers and never share an OTP.'
                : 'Untuk bantuan pembayaran, kirim nomor pesanan, metode pembayaran, dan status yang terlihat di aplikasi. Anda boleh melampirkan bukti transfer, tetapi tutupi nomor sensitif dan jangan membagikan OTP.';
        }
        if ($this->matches($combined, 'order status|track|tracking|pesanan|order|status pesanan|delivery|pengiriman')) {
            return $language === 'en'
                ? 'I can help check an order. Please send the order number and tell me what you want to know: payment, confirmation, schedule, or delivery. An admin confirms live order data.'
                : 'Saya bisa membantu memeriksa pesanan. Kirim nomor pesanan dan jelaskan yang ingin diketahui: pembayaran, konfirmasi, jadwal, atau pengiriman. Admin mengonfirmasi data pesanan terbaru.';
        }
        if ($this->matches($combined, 'price|cost|budget|how much|harga|biaya|anggaran|berapa|murah|mahal|promo|discount|diskon|voucher')) {
            return $language === 'en'
                ? 'I can help compare packages and budget options. Please share your event date, city or venue, estimated guests, and preferred style. Prices and availability are confirmed by an admin after checking the details.'
                : 'Saya bisa membantu membandingkan paket dan anggaran. Kirim tanggal acara, kota atau lokasi, perkiraan tamu, dan gaya yang disukai. Harga serta ketersediaan akan dikonfirmasi admin setelah detail diperiksa.';
        }
        if ($this->matches($combined, 'available|availability|date|schedule|tanggal|tersedia|ketersediaan|jadwal')) {
            return $language === 'en'
                ? 'To check availability, please tell me the event date, city or venue, and the decoration package or concept. I will keep those details in this thread while an admin confirms the schedule.'
                : 'Untuk memeriksa ketersediaan, kirim tanggal acara, kota atau lokasi, serta paket atau konsep dekorasi. Detail itu akan tetap tersimpan di percakapan ini sampai admin mengonfirmasi jadwal.';
        }
        if ($this->matches($combined, 'flower|flowers|rose|lily|tulip|orchid|mawar|melati|bunga|warna|color|theme|tema|concept|konsep')) {
            return $language === 'en'
                ? 'Beautiful choice! Tell me the preferred flowers or colors, wedding style, venue, and budget. I can help narrow the concept and the admin can match it with available catalogue options.'
                : 'Pilihan yang indah! Ceritakan bunga atau warna yang disukai, gaya pernikahan, lokasi, dan anggarannya. Saya bantu menyaring konsep, lalu admin mencocokkannya dengan pilihan katalog yang tersedia.';
        }
        if ($this->matches($combined, 'location|address|where|area|city|lokasi|alamat|dimana|di mana|kota|luar kota')) {
            return $language === 'en'
                ? 'We can discuss decoration for your venue and city. Please send the venue or city and event date; an admin will confirm the service area, travel needs, and availability.'
                : 'Kita bisa membahas dekorasi untuk lokasi dan kota acara Anda. Kirim lokasi atau kota serta tanggal acara; admin mengonfirmasi area layanan, kebutuhan perjalanan, dan ketersediaannya.';
        }
        if ($this->matches($combined, 'review|rating|ulasan|rating|testimoni')) {
            return $language === 'en'
                ? 'You can leave a review after your eligible order. Tell me if you need help finding the review page or attaching a photo, and I will guide you.'
                : 'Ulasan dapat diberikan setelah pesanan yang memenuhi syarat. Beri tahu jika Anda membutuhkan bantuan menemukan halaman ulasan atau melampirkan foto, dan saya akan memandu.';
        }
        if ($this->matches($combined, 'book|booking|order|reserve|reservation|pesan|pemesanan|booking|reservasi|beli')) {
            return $language === 'en'
                ? 'I can guide your booking: choose a catalogue or package, share the date and venue, discuss the theme, review the quotation, and confirm the deposit. Which step are you at now?'
                : 'Saya bisa memandu pemesanan: pilih katalog atau paket, kirim tanggal dan lokasi, bahas tema, tinjau penawaran, lalu konfirmasi uang muka. Anda sedang berada di langkah yang mana?';
        }

        return $language === 'en'
            ? "Thanks for explaining that.{$context} I can help with flowers, packages, prices, availability, event details, orders, payments, accounts, reviews, and admin support. Please tell me the most important detail first."
            : "Terima kasih sudah menjelaskannya.{$context} Saya bisa membantu bunga, paket, harga, ketersediaan, detail acara, pesanan, pembayaran, akun, ulasan, dan bantuan admin. Silakan tuliskan detail yang paling penting terlebih dahulu.";
    }

    private function matches(string $text, string $pattern): bool
    {
        return preg_match('/(?:'.$pattern.')/iu', $text) === 1;
    }
}
