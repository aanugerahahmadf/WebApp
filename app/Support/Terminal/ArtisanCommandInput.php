<?php

namespace App\Support\Terminal;

/**
 * Normalisasi input perintah artisan dari web terminal.
 *
 * Kenapa perlu kelas ini:
 *
 * `Artisan::call()` sudah dipanggil DARI DALAM konteks artisan, jadi input
 * yang benar hanya nama command + argumennya (`migrate:status`). Admin yang
 * sudah terbiasa dengan terminal sungguhan hampir selalu mengetik prefiks
 * lengkap `php artisan migrate` -- persis seperti yang diketik di shell.
 *
 * Tanpa normalisasi, `php artisan migrate` diteruskan apa adanya ke Artisan,
 * yang memperlakukannya sebagai command bernama `php`, sehingga hasilnya:
 * `The command "php" does not exist.` -- membingungkan karena command-nya
 * sendiri sebenarnya valid.
 *
 * Jadi kelas ini membuang lapisan shell yang tidak perlu itu, sebelum
 * pengecekan blacklist dan sebelum pemanggilan Artisan.
 */
class ArtisanCommandInput
{
    /**
     * Prefiks yang sering diketik admin, yang harus dibuang sebelum
     * meneruskan ke Artisan.
     *
     * - `php artisan`, `php8.3 artisan`, `php.exe artisan` -> interpreter shell
     * - `artisan`, `./artisan`, `.\artisan`               -> path file artisan
     *
     * @var list<string>
     */
    private const SHELL_PREFIXES = [
        // Interpreter + file artisan: "php artisan ...", "php8.3 artisan ..."
        '#^\.?(?:[/\x5C])?(?:php(?:\.exe)?|php\d+(?:\.\d+)?)\s+artisan(?:\.bat)?\s*#i',
        // Path file artisan saja: "./artisan ...", ".\artisan ...", "/artisan ..."
        //
        // Lookahead `(?=\s|$)` itu wajib, bukan tambahan opsional. Tanpa itu,
        // pola ini ikut cocok dengan command yang NAMANYA diawali "artisan",
        // sehingga `artisan:foo` berubah jadi `:foo` -- command yang tadinya
        // valid jadi rusak. Prefiks hanya boleh dikenali kalau kata itu berdiri
        // sendiri, yaitu diikuti spasi atau berada di akhir string.
        '#^\.?(?:[/\x5C])?artisan(?:\.bat)?(?=\s|$)\s*#i',
    ];

    /**
     * Rapatkan whitespace jadi satu spasi, buang spasi di tepi, lalu buang
     * prefiks shell berulang kali kalau ada.
     *
     * Penting: `Artisan::call()` menerima string dan memecahkannya sendiri,
     * jadi kita TIDAK mem-parse argumen manual di sini. Kita hanya membersihkan
     * lapisan luar dan menjaga argumen asli tetap utuh -- termasuk tanda kutip
     * yang mungkin dipakai user, mis. `migrate --path="database/migrations"`.
     */
    public static function normalize(string $input): string
    {
        // Rapatkan newline/tab/spasi berlebih jadi satu spasi.
        $clean = trim(preg_replace('/\s+/u', ' ', $input) ?? '');

        if ($clean === '') {
            return '';
        }

        // Buang prefiks shell berulang, mis. "php artisan artisan migrate".
        $changed = true;

        while ($changed) {
            $changed = false;

            foreach (self::SHELL_PREFIXES as $pattern) {
                $stripped = preg_replace($pattern, '', $clean, 1);

                if (is_string($stripped) && $stripped !== $clean) {
                    $clean = ltrim($stripped);
                    $changed = true;
                }
            }
        }

        return trim($clean);
    }

    /**
     * Nama command tanpa argumen, dipakai untuk pengecekan blacklist.
     *
     * Contoh: `php artisan migrate --database=mysql` -> `migrate`
     */
    public static function commandName(string $input): string
    {
        $parts = explode(' ', self::normalize($input));

        return $parts[0] ?? '';
    }
}