<?php

namespace App\Support\Chat;

use App\Models\Message\Message;

class BotReplyLocalizer
{
    public static function display(Message $message): string
    {
        $text = (string) ($message->message ?? '');
        $meta = $message->meta ?? [];

        if (empty($meta['is_bot'])) {
            return $text;
        }

        $key = $meta['bot_reply_key'] ?? self::identify($text);
        $data = $meta['bot_reply_data'] ?? [];

        return self::translate($key, is_array($data) ? $data : [], $text, app()->getLocale());
    }

    public static function identify(string $text, ?string $category = null): string
    {
        if ($category && in_array($category, ['bug_report', 'account_issue', 'order_help', 'payment_issue', 'decor_consultation', 'general_question'], true)) {
            return $category;
        }

        return match (true) {
            str_contains($text, 'melaporkan bug') => 'bug_report',
            str_contains($text, 'masalah akun') => 'account_issue',
            str_contains($text, 'terkait pesanan') => 'order_help',
            str_contains($text, 'Masalah pembayaran') => 'payment_issue',
            str_contains($text, 'konsultasi dekorasi pernikahan') => 'decor_consultation',
            str_contains($text, 'siap menjawab pertanyaan') => 'general_question',
            str_contains($text, 'menerima pesanan Anda') => 'new_order',
            str_contains($text, 'pilihan yang luar biasa') => 'catalog_inquiry',
            str_contains($text, 'Mohon maaf atas ketidaknyamanannya') => 'urgent_complaint',
            str_contains($text, 'berbagai koleksi bunga') => 'flower_theme',
            str_contains($text, 'Terkait biaya') => 'price_budget',
            str_contains($text, 'Kantor utama kami') => 'location',
            str_contains($text, 'Prosedurnya sangat simpel') => 'booking',
            str_contains($text, 'Senang sekali bisa menyapa') => 'greeting',
            str_contains($text, 'Sudah menjadi komitmen') => 'gratitude',
            str_contains($text, 'memanggil Admin') => 'human_admin',
            default => 'fallback',
        };
    }

    /** Render bot content from a stable key so language changes update old replies too. */
    private static function translate(string $key, array $data, string $original, string $locale): string
    {
        if (! str_starts_with($locale, 'en')) {
            return $original;
        }

        $name = $data['user_name'] ?? self::extractName($original) ?? 'there';
        $greeting = self::englishGreeting($original);
        $orderNumber = $data['order_number'] ?? 'your order';
        $itemName = $data['item_name'] ?? 'this item';

        return match ($key) {
            'bug_report' => "{$greeting} {$name}! Thank you for reporting this bug. We truly appreciate your report. Please describe the problem in detail, including:\n\n1. What were you doing when the bug happened?\n2. What did you expect to happen?\n3. What actually happened?\n4. Attach a screenshot if possible.\n\nOur technical team will review this report shortly. We prioritize your message right now. 🙏",
            'account_issue' => "{$greeting} {$name}! We understand that account issues can be frustrating, and we are ready to help. Please tell us about the issue, for example:\n\n• Unable to sign in\n• Forgotten password\n• Account verification problem\n• Profile data not saved\n\nWe will help resolve it as soon as possible. Have a wonderful day! ✨",
            'order_help' => "{$greeting} {$name}! We are ready to help with your order. To assist you faster, please include:\n\n1. Your order number\n2. Your current order status\n3. The issue you are experiencing\n\nYou can also share the order details using the attachment button and selecting Order. Our admin will respond shortly! 📋",
            'payment_issue' => "{$greeting} {$name}! Payment issues are important, and we prioritize them. Please explain:\n\n1. The payment method used (VA/QRIS/Transfer)\n2. The current payment status\n3. Whether you have completed the transfer\n4. A payment receipt, if available\n\nWe will verify your payment shortly. We prioritize your message right now. 🙏",
            'decor_consultation' => "{$greeting} {$name}! We are delighted to help with your wedding decoration consultation! 🌸\n\nPlease tell us about:\n1. Your wedding theme\n2. Dominant colors\n3. Your available budget\n4. Event date\n\nOur admin will recommend the best decoration for your special day. Feel free to browse our catalogue too! 💐",
            'general_question' => "{$greeting} {$name}! We are ready to answer your questions. Please ask us anything about our services. Our admin will respond shortly. 🙋",
            'new_order' => "{$greeting} {$name}! We are excited to receive your order ({$orderNumber}). 😍 Our team is checking the schedule and technical details to make sure everything is perfect. We will contact you shortly with the next steps. Thank you for trusting us with your special moment!",
            'catalog_inquiry' => "Hello {$name}, that is a wonderful choice! {$itemName} is currently on trend and highly sought after. Our admin is preparing availability details for your event date. While waiting, do you have a preferred flower color for this theme?",
            'urgent_complaint' => "We are sorry for the inconvenience, {$name}. We understand this is important to you. Your message has been marked as high priority. Our senior admin will join this conversation shortly to assist you directly. We prioritize your message right now. 🙏",
            'flower_theme' => "That sounds interesting! We have a wide collection of fresh, premium flowers. Our admin will send references for concepts and flower combinations that suit your preferences, {$name}. Please wait a moment while we gather relevant portfolio photos. 🌸",
            'price_budget' => "Hello {$name}! Regarding pricing, we are flexible and offer packages that can be adjusted to your budget. The good news is that there are exclusive promotions on the Voucher & Promo page. Our admin will provide the most competitive estimate shortly! 💰",
            'location' => "Of course, {$name}! Our main office is located in Rajasinga, Terisi, Indramayu Regency, West Java. We serve both local and out-of-town decoration needs. If you would like an in-person consultation, our admin will send the location details shortly. 📍",
            'booking' => "Of course, {$name}! The process is simple: choose a package, discuss the theme, pay a deposit to secure the date, and we will handle the rest. You can start from the Flower Decoration Package Catalogue. Our admin will guide you step by step shortly. 📝",
            'greeting' => "{$greeting}, {$name}! It is lovely to hear from you. How can we help bring your dream wedding to life today? We are ready to provide the best decoration solution! 😊",
            'gratitude' => "You are welcome, {$name}! Providing the best service is our commitment. Is there anything else you would like to know? Have a wonderful day! ✨",
            'human_admin' => "Alright {$name}, I am contacting our admin to join this conversation. Please wait a moment; we will assist you directly shortly. 🙏",
            default => "{$greeting}, {$name}! Thank you for contacting us. Your message is important to us. Our admin is reviewing your request and will provide the most accurate answer shortly. While waiting, please browse our latest Flower Decoration Package and Flower Catalogues. 🙏",
        };
    }

    private static function extractName(string $text): ?string
    {
        preg_match('/(?:Selamat (?:pagi|siang|sore|malam)|Good (?:morning|afternoon|evening|night))\s+([^!,.]+)/iu', $text, $matches);

        return isset($matches[1]) ? trim($matches[1]) : null;
    }

    private static function englishGreeting(string $text): string
    {
        return match (true) {
            str_contains($text, 'Selamat pagi') || str_contains($text, 'Good morning') => 'Good morning',
            str_contains($text, 'Selamat siang') || str_contains($text, 'Good afternoon') => 'Good afternoon',
            str_contains($text, 'Selamat sore') || str_contains($text, 'Good evening') => 'Good evening',
            default => 'Good night',
        };
    }
}
