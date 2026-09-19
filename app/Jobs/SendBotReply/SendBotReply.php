<?php

namespace App\Jobs\SendBotReply;

use App\Models\Message\Message;
use App\Models\User\User;
use App\Support\Chat\BotReplyLocalizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendBotReply implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $messageId;

    protected $locale;

    public function __construct(int $messageId, ?string $locale = null)
    {
        $this->messageId = $messageId;
        $this->locale = $locale ?? app()->getLocale();
    }

    public function handle(): void
    {
        // Persist a stable Indonesian source. The UI localizes bot replies by
        // template key using the language currently selected by the user.
        app()->setLocale('id');

        $userMessage = Message::find($this->messageId);
        if (! $userMessage) {
            return;
        }

        $inbox = $userMessage->inbox;

        $sender = User::find($userMessage->user_id);

        if ($userMessage->meta && isset($userMessage->meta['is_bot']) && $userMessage->meta['is_bot']) {
            return;
        }

        $text = strtolower($userMessage->message ?? '');
        $reply = '';

        $userName = $sender ? explode(' ', $sender->name)[0] : __('Kak');

        $hour = now()->hour;
        if ($hour < 11) {
            $greeting = __('Selamat pagi');
        } elseif ($hour < 15) {
            $greeting = __('Selamat siang');
        } elseif ($hour < 19) {
            $greeting = __('Selamat sore');
        } else {
            $greeting = __('Selamat malam');
        }

        $warmClosure = __('Semoga hari Anda menyenangkan! ✨');
        $urgentClosure = __('Kami prioritaskan pesan Anda sekarang juga. 🙏');

        // Check inbox meta for CS category
        $inboxMeta = $inbox->meta ?? [];
        $csCategory = $inboxMeta['cs_category'] ?? null;

        switch (true) {
            // CS Category: Bug Report
            case str_contains($text, '🐛') || $csCategory === 'bug_report':
                $reply = __(':greeting :userName! Terima kasih telah melaporkan bug. Kami sangat menghargai laporan Anda. Mohon jelaskan masalah yang Anda alami secara detail, termasuk:\n\n1. Apa yang sedang Anda lakukan saat bug terjadi?\n2. Apa yang Anda harapkan terjadi?\n3. Apa yang sebenarnya terjadi?\n4. Lampirkan screenshot jika memungkinkan.\n\nTim teknis kami akan segera menindaklanjuti laporan ini. :urgentClosure', [
                    'greeting' => $greeting,
                    'userName' => $userName,
                    'urgentClosure' => $urgentClosure,
                ]);
                break;

            // CS Category: Account Issue
            case str_contains($text, '👤') || $csCategory === 'account_issue':
                $reply = __(':greeting :userName! Kami paham masalah akun bisa sangat menjengkelkan. Kami siap membantu Anda. Mohon jelaskan masalah akun yang Anda alami, seperti:\n\n• Tidak bisa login\n• Lupa password\n• Verifikasi akun bermasalah\n• Data profil tidak tersimpan\n\nKami akan membantu menyelesaikan masalah Anda secepat mungkin. :warmClosure', [
                    'greeting' => $greeting,
                    'userName' => $userName,
                    'warmClosure' => $warmClosure,
                ]);
                break;

            // CS Category: Order Help
            case str_contains($text, '📦') || $csCategory === 'order_help':
                $reply = __(':greeting :userName! Tentu, kami siap membantu Anda terkait pesanan. Untuk membantu Anda lebih cepat, mohon sertakan:\n\n1. Nomor pesanan Anda\n2. Status pesanan saat ini\n3. Masalah yang Anda alami\n\nAnda juga bisa membagikan detail pesanan dengan menekan tombol lampiran dan pilih "Order". Admin kami akan segera merespons! 📋', [
                    'greeting' => $greeting,
                    'userName' => $userName,
                ]);
                break;

            // CS Category: Payment Issue
            case str_contains($text, '💳') || $csCategory === 'payment_issue':
                $reply = __(':greeting :UserName! Masalah pembayaran sangat penting dan kami prioritaskan. Mohon jelaskan:\n\n1. Metode pembayaran yang digunakan (VA/QRIS/Transfer)\n2. Status pembayaran saat ini\n3. Apakah sudah melakukan transfer?\n4. Lampirkan bukti transfer jika ada\n\nKami akan segera memverifikasi pembayaran Anda. :urgentClosure', [
                    'greeting' => $greeting,
                    'UserName' => $userName,
                    'urgentClosure' => $urgentClosure,
                ]);
                break;

            // CS Category: Decoration Consultation
            case str_contains($text, '🌸') || $csCategory === 'decor_consultation':
                $reply = __(':greeting :userName! Senang sekali bisa membantu konsultasi dekorasi pernikahan Anda! 🌸\n\nCeritakan tentang:\n1. Tema pernikahan Anda\n2. Warna dominan\n3. Anggaran yang tersedia\n4. Tanggal acara\n\nAdmin kami akan membantu memberikan rekomendasi dekorasi terbaik untuk hari spesial Anda. Jangan ragu untuk melihat katalog kami juga ya! 💐', [
                    'greeting' => $greeting,
                    'userName' => $userName,
                ]);
                break;

            // CS Category: General Question
            case str_contains($text, '❓') || $csCategory === 'general_question':
                $reply = __(':greeting :userName! Kami siap menjawab pertanyaan Anda. Silakan tanyakan apa saja terkait layanan kami. Admin kami akan merespons dalam beberapa saat. 🙋', [
                    'greeting' => $greeting,
                    'userName' => $userName,
                ]);
                break;

            // A. Context: New Order (High Priority)
            case $userMessage->meta && isset($userMessage->meta['is_order']) && $userMessage->meta['is_order']:
                $orderNumber = $userMessage->meta['order_number'];
                $reply = __('Wah, :greeting :userName! Kami sangat antusias menerima pesanan Anda (:orderNumber). 😍 Tim kami sedang melakukan pengecekan jadwal dan detail teknis untuk memastikan semuanya sempurna. Kami akan segera menghubungi Anda untuk langkah selanjutnya. Terima kasih telah mempercayakan momen spesial Anda kepada kami!', [
                    'greeting' => $greeting,
                    'userName' => $userName,
                    'orderNumber' => $orderNumber,
                ]);
                break;

            // B. Context: Product/Package Discovery
            case $userMessage->meta && isset($userMessage->meta['type']):
                $name = $userMessage->meta['name'];
                $reply = __('Halo :userName, pilihan yang luar biasa! :name memang sedang menjadi tren dan sangat diminati. Admin kami sedang menyiapkan detail ketersediaan untuk tanggal acara Anda. Sambil menunggu, apakah :userName punya preferensi warna bunga tertentu untuk tema ini?', [
                    'userName' => $userName,
                    'name' => $name,
                ]);
                break;

            // C. Intent: Urgent / Complaints
            case preg_match('/(urgent|cepat|darurat|lama|komplain|masalah|kecewa|tolong|help)/', $text):
                $reply = __('Mohon maaf atas ketidaknyamanannya, :userName. Kami memahami ini sangat penting bagi Anda. Saya telah menandai pesan ini sebagai Prioritas Utama. Admin senior kami akan segera masuk ke percakapan ini untuk membantu Anda secara langsung. :urgentClosure', [
                    'userName' => $userName,
                    'urgentClosure' => $urgentClosure,
                ]);
                break;

            // D. Intent: Specific Flowers / Themes
            case preg_match('/(mawar|rose|lily|tulip|anggrek|melati|bunga|warna|tema|konsep)/', $text):
                $reply = __('Menarik sekali! Kami memiliki berbagai koleksi bunga segar dan premium. Admin kami akan mengirimkan beberapa referensi konsep dan kombinasi bunga yang cocok dengan keinginan :userName. Tunggu sebentar ya, kami sedang mengumpulkan foto portofolio yang relevan. 🌸', [
                    'userName' => $userName,
                ]);
                break;

            // E. Intent: Price & Budget (Sales Conversion)
            case preg_match('/(harga|berapa|price|biaya|budget|mahal|murah|diskon|promo|voucher)/', $text):
                $reply = __('Halo :userName! Terkait biaya, kami sangat fleksibel dan memiliki paket yang bisa disesuaikan dengan budget Anda. Kabar baiknya, ada beberapa promo eksklusif yang bisa Anda cek di halaman **Voucher & Promo**. Admin kami akan segera memberikan estimasi penawaran yang paling kompetitif untuk Anda! 💰', [
                    'userName' => $userName,
                ]);
                break;

            // F. Intent: Location & Logistics
            case preg_match('/(lokasi|alamat|dimana|where|tempat|kantor|area|luar kota)/', $text):
                $officeAddress = __('Rajasinga, Kec. Terisi, Kabupaten Indramayu, Jawa Barat');

                $reply = __('Tentu :userName! Kantor utama kami berlokasi di **:address**. Kami melayani dekorasi untuk area lokal maupun luar kota. Jika :userName ingin berkunjung untuk konsultasi tatap muka, admin kami akan segera memberikan titik koordinatnya. 📍', [
                    'userName' => $userName,
                    'address' => $officeAddress,
                ]);
                break;

            // G. Intent: Booking & Procedure
            case preg_match('/(pesan|booking|order|beli|cara|prosedur|syarat)/', $text):
                $reply = __('Tentu, :userName! Prosedurnya sangat simpel: Pilih paket, konsultasi tema, DP untuk kunci tanggal, dan sisanya kami yang urus. Anda bisa mulai dengan memilih paket di halaman **Katalog Paket Dekorasi Bunga**. Admin kami akan memandu Anda langkah demi langkah sebentar lagi. 📝', [
                    'userName' => $userName,
                ]);
                break;

            // H. Intent: Greetings & Small Talk
            case preg_match('/(halo|hi|hey|pagi|siang|sore|malam|permisi|apa kabar|assalamu)/', $text):
                $reply = __(':greeting, :userName! Senang sekali bisa menyapa Anda. Ada yang bisa kami bantu untuk mewujudkan pernikahan impian Anda hari ini? Kami siap memberikan solusi dekorasi terbaik! 😊', [
                    'greeting' => $greeting,
                    'userName' => $userName,
                ]);
                break;

            // I. Intent: Gratitude
            case preg_match('/(terima kasih|thanks|makasih|thx|tq|oke|ok|sip)/', $text):
                $reply = __('Sama-sama, :userName! Sudah menjadi komitmen kami untuk memberikan layanan terbaik. Ada hal lain yang ingin Anda ketahui? :warmClosure', [
                    'userName' => $userName,
                    'warmClosure' => $warmClosure,
                ]);
                break;

            // J. Intent: Manual Admin Request
            case preg_match('/(admin|manusia|orang|balas|tanya admin|panggil)/', $text):
                $reply = __('Baik :userName, saya sedang memanggil Admin kami untuk bergabung ke percakapan ini. Mohon tunggu sebentar ya, kami akan segera melayani Anda secara langsung. 🙏', [
                    'userName' => $userName,
                ]);
                break;

            // K. Fallback
            default:
                $reply = __(':greeting, :userName! Terima kasih telah menghubungi kami. Pesan Anda sangat berharga bagi kami. Admin kami sedang mempelajari permintaan Anda dan akan segera memberikan jawaban yang paling akurat dalam beberapa saat. Sambil menunggu, silakan lihat koleksi terbaru kami di halaman **Katalog Paket Dekorasi Bunga** dan **Katalog Bunga**. 🙏', [
                    'greeting' => $greeting,
                    'userName' => $userName,
                ]);
                break;
        }

        // Bot templates use escaped newlines inside single-quoted PHP strings.
        // Persist real line breaks so every client (web, mobile, notifications)
        // receives cleanly formatted text.
        $reply = str_replace(['\\r\\n', '\\n', '\\r'], ["\r\n", "\n", "\r"], $reply);

        $admin = User::whereHas('roles', function ($q) {
            $q->where('name', 'super_admin');
        })->first();

        if ($admin) {
            Message::create([
                'inbox_id' => $inbox->id,
                'user_id' => $admin->id,
                'message' => $reply,
                'meta' => [
                    'is_bot' => true,
                    'bot_reply_key' => BotReplyLocalizer::identify($reply, $csCategory),
                    'bot_reply_data' => [
                        'user_name' => $userName,
                        'order_number' => $userMessage->meta['order_number'] ?? null,
                        'item_name' => $userMessage->meta['name'] ?? null,
                    ],
                ],
            ]);
        }
    }
}
