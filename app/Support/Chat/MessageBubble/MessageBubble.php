<?php

namespace App\Support\Chat\MessageBubble;

/**
 * Membangun payload JSON untuk gelembung aksi pesan (lihat
 * resources/views/Shared/components/message-bubble/).
 *
 * Kenapa kelas, bukan Closure di blade: daftar aksi User dan Admin punya bentuk
 * yang sama tapi isinya berbeda, dan bagian yang obnoxious cukup merepotkan --
 * merender ikon Heroicon jadi HTML, mengemas ulang jadi JSON. Kalau itu ditulis
 * inline di dua blade, aturan yang sama akan disimpan dua kali dan pasti
 * berbeda satu baris saat salah satunya diubah.
 */
class MessageBubble
{
    /**
     * Emoji reaksi cepat. Satu daftar untuk semua panel supaya urutan dan
     * jumlahnya sama di User dan Admin.
     *
     * Ditulis sebagai escape \u{}, bukan emoji langsung, supaya file ini tetap
     * ASCII murni. Sources yang ditulis dengan encoding UTF-8 tapi dibaca sebagai
     * Windows-1252 akan mengubah emoji jadi tanda tanya -- persis yang terjadi
     * di baris lama blade chat sebelum setter ini dibuat.
     *
     * @return array<int, string>
     */
    public static function quickEmojis(): array
    {
        return [
            "\u{1F44D}",      // thumbs up
            "\u{2764}\u{FE0F}", // heart
            "\u{1F602}",      // joy
            "\u{1F62E}",      // open mouth
            "\u{1F622}",      // crying
            "\u{1F621}",      // angry
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $actions
     * @return array<string, mixed>
     */
    public static function payload(int $messageId, array $actions, ?array $emojis = null): array
    {
        return [
            'id' => $messageId,
            'emojis' => $emojis ?? self::quickEmojis(),
            'actions' => array_values($actions),
        ];
    }

    /**
     * Aksi yang memanggil method Livewire.
     *
     * @param  array<int, mixed>  $args
     * @return array<string, mixed>
     */
    public static function wireAction(
        string $key,
        string $label,
        string $icon,
        string $method,
        array $args = [],
        string $tone = 'default',
    ): array {
        return [
            'key' => $key,
            'type' => 'wire',
            'label' => $label,
            'icon' => self::icon($icon),
            'method' => $method,
            'args' => array_values($args),
            'tone' => $tone,
        ];
    }

    /**
     * Aksi yang selesai di sisi peramban. Satu-satunya saat ini adalah "Salin",
     * karena menyalin teks tidak perlu menyentuh server sama sekali.
     *
     * @return array<string, mixed>
     */
    public static function copyAction(string $key, string $label, string $icon, string $text): array
    {
        return [
            'key' => $key,
            'type' => 'copy',
            'label' => $label,
            'icon' => self::icon($icon),
            'copy' => $text,
            'method' => null,
            'args' => [],
            'tone' => 'default',
        ];
    }

    /**
     * Heroicon dirender di PHP lalu dikirim sebagai HTML mentah, karena
     * gelembung dibangun di JavaScript. `<x-filament::icon :icon="...">` tidak
     * mungkin dipakai di sini: atributnya dibekukan waktu render Blade, bukan
     * saat Alpine mengevaluasi x-for.
     *
     * Ikon yang tidak ditemukan mengembalikan string kosong, bukan melempar.
     * Halaman chat tidak boleh gagal total hanya karena ada satu nama ikon yang
     * salah ketik -- gelembung tetap muncul, hanya ikonnya yang kosong.
     */
    public static function icon(string $name, string $class = 'h-4 w-4'): string
    {
        try {
            // BladeUI\Icons\Svg hanya mengimplementasikan Htmlable, tidak punya
            // __toString -- jadi toHtml() yang dipanggil, bukan cast ke string.
            return svg($name, $class)->toHtml();
        } catch (\Throwable) {
            return '';
        }
    }
}
