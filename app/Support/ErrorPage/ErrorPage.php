<?php

namespace App\Support\ErrorPage;

/**
 * Sumber tunggal untuk isi halaman error (desain "glass" + animasi).
 *
 * KENAPA KELAS, BUKAN ARRAY DI BLADE:
 *
 *   - Tiga kelompok view memakai halaman yang sama persis (Welcome, User,
 *     Admin). Kalau teks dan warnanya ditulis tiga kali, cepat atau lambat
 *     salah satu tidak ikut berubah -- dan yang tidak ikut berubah biasanya
 *     yang paling jarang dibuka, jadi tidak ada yang sadar.
 *   - `App\Exceptions\Handler\Handler` juga perlu tahu view mana yang dipakai
 *     untuk sebuah status. Itu keputusan yang sama dengan keputusan visual,
 *     jadi keduanya harus membaca satu sumber, bukan masing-masing menulis
 *     daftar sendiri.
 *   - Aksen per status (warna kode, warna orb) dan aksen per panel (warna
 *     tombol utama) dipisah supaya "halaman 404" tetap merah/ungu saat
 *     panelnya ganti.
 *
 * CATATAN TENTANG DB:
 *
 *   Halaman error adalah tempat terakhir yang boleh bergantung pada
 *   database -- kalau error-nya justru berasal dari koneksi database, halaman
 *   error yang melakukan query akan ikut timeout. Karena itu kelas ini tidak
 *   menyentuh DB sama sekali: nama brand diambil dari `config()`, bukan dari
 *   `AppFont` (yang meng-cache Setting dari database).
 */
class ErrorPage
{
    /** Panel yang sedang dilayani. */
    public const PANEL_ADMIN = 'admin';

    public const PANEL_STOREFRONT = 'storefront';

    /**
     * Aksen per status: [ warna-1, warna-2, warna-teks-di-atasnya ].
     *
     * Warna ketiga dipakai untuk teks di atas gradien (tombol utama).
     * Amber/oranye butuh teks gelap; warna yang lebih gelap butuh teks putih.
     */
    private const ACCENTS = [
        401 => ['#f59e0b', '#fbbf24', '#3b2600'],
        402 => ['#8b5cf6', '#a78bfa', '#ffffff'],
        403 => ['#f43f5e', '#fb7185', '#ffffff'],
        404 => ['#0ea5e9', '#38bdf8', '#ffffff'],
        419 => ['#f97316', '#fb923c', '#2b1000'],
        429 => ['#d946ef', '#e879f9', '#2b0033'],
        500 => ['#ef4444', '#f87171', '#ffffff'],
        503 => ['#6366f1', '#818cf8', '#ffffff'],
    ];

    /** Warna brand per panel: storefront = emas (primary panel Welcome/User),
     *  admin = indigo (primary panel Admin).
     */
    private const BRANDS = [
        self::PANEL_STOREFRONT => ['#ca8a04', '#facc15', '#3b2600'],
        self::PANEL_ADMIN => ['#4f46e5', '#818cf8', '#ffffff'],
    ];

    /** Ikon tombol. */

    private const ICON_ARROW_RIGHT = '<path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 12m0 0-7.5-7.5M21 12l-7.5 7.5" />';

    private const ICON_RETRY = '<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />';

    /**
     * Judul per status (bahasa Inggris sebagai sumber).
     *
     * SENGAJA TIDAK ADA KALIMAT PENJELAS di sini.
     *
     * Halaman error cukup punya tiga hal: kode, judul, dan tombol yang bisa
     * diklik. Kalimat penjelasan pernah jadi tempat dua kebocoran sekaligus:
     * pesan teknis dari exception ("The route xyz could not be found") yang
     * tidak berguna untuk pengguna, dan teks generik yang menambah tinggi
     * halaman tanpa menambah informasi. Keduanya dihapus.
     */
    private const COPY = [
        401 => ['title' => 'Unauthorized'],
        402 => ['title' => 'Payment Required'],
        403 => ['title' => 'Forbidden'],
        404 => ['title' => 'Not Found'],
        419 => ['title' => 'Page Expired'],
        429 => ['title' => 'Too Many Requests'],
        500 => ['title' => 'Server Error'],
        503 => ['title' => 'Service Unavailable'],
    ];

    /** Judul untuk status di luar daftar di atas. */
    private const GENERIC_TITLE = 'Something Went Wrong';

    /**
     * Panel yang sedang dilayani request.
     *
     * Panel admin hidup di /admin/*, sisanya storefront. Method ini dipanggil
     * dari dua tempat (Handler dan view), jadi aturan "request mana yang
     *milik admin" hanya ada di sini.
     */
    public static function panel(): string
    {
        if (! app()->bound('request')) {
            return self::PANEL_STOREFRONT;
        }

        $path = '/'.ltrim((string) request()->path(), '/');

        if ($path === '/admin' || str_starts_with($path, '/admin/')) {
            return self::PANEL_ADMIN;
        }

        return self::PANEL_STOREFRONT;
    }

    /**
     * Namespace view yang dipakai untuk panel aktif ("Admin" atau "Welcome").
     */
    public static function viewNamespace(): string
    {
        return self::panel() === self::PANEL_ADMIN ? 'Admin' : 'Welcome';
    }

    /**
     * Urutan view kandidat untuk sebuah status.
     *
     * Panel aktif didahulukan, lalu dua panel lain sebagai jaring pengaman:
     * kalau salah satu set view dihapus, halaman error TIDAK ikut hilang --
     * ia jatuh ke view panel berikutnya, lalu ke bawaan Laravel.
     */
    public static function viewCandidates(int $status): array
    {
        $primary = self::viewNamespace();

        return array_values(array_unique([
            "{$primary}.errors.{$status}.{$status}",
            'Welcome.errors.'.$status.'.'.$status,
            'User.errors.'.$status.'.'.$status,
            'Admin.errors.'.$status.'.'.$status,
        ]));
    }

    /**
     * Seluruh isi halaman untuk sebuah status.
     *
     * TIDAK ADA parameter exception, dan itu disengaja.
     *
     * Pesan exception tidak pernah ditampilkan ke pengguna. Yang muncul di
     * produksi adalah kalimat seperti "The route xyz could not be found" --
     * membocorkan struktur routing ke pengunjung dan tidak berguna bagi
     * mereka. Detail teknisnya ada di log, bukan di halaman.
     *
     * @param  int  $status  Kode status HTTP.
     * @param  string|null  $panel  Paksa panel (untuk pengujian).
     * @return array<string, mixed>
     */
    public static function for(int $status, ?string $panel = null): array
    {
        $status = $status > 0 ? $status : 404;
        $panel = in_array($panel, [self::PANEL_ADMIN, self::PANEL_STOREFRONT], true)
            ? $panel
            : self::panel();

        $copy = self::COPY[$status] ?? [
            'title' => self::GENERIC_TITLE,
        ];

        $accent = self::ACCENTS[$status] ?? self::ACCENTS[404];
        $brand = self::BRANDS[$panel] ?? self::BRANDS[self::PANEL_STOREFRONT];

        return [
            'code' => $status,
            'panel' => $panel,
            'title' => (string) __($copy['title']),
            'accent' => [$accent[0], $accent[1]],
            'accentRgb' => [self::rgb($accent[0]), self::rgb($accent[1])],
            'brand' => [$brand[0], $brand[1]],
            'brandRgb' => [self::rgb($brand[0]), self::rgb($brand[1])],
            'brandOn' => $brand[2],
            'actions' => self::actions($status, $panel),
        ];
    }

    /**
     * Tombol yang ditawarkan.
     *
     * CUMA SATU tombol per halaman:
     *
     *   - 401 -> "Masuk" (ke pintu sign in panel yang sedang dilayani)
     *   - selain itu -> "Coba Lagi" (muat ulang halaman)
     *
     * Tombol "Kembali ke Beranda" SENGAJA TIDAK ADA.
     *
     * Alasannya: halaman error hampir selalu muncul dari tautan yang salah
     * atau aksi yang gagal -- dan "kembali ke beranda" membuang konteksnya
     * (katalog, keranjang, halaman pesanan). "Coba Lagi" selalu berguna
     * dan tidak membuang apa pun.
     *
     * @return array<int, array<string, mixed>>
     */
    private static function actions(int $status, string $panel): array
    {
        $isAdmin = $panel === self::PANEL_ADMIN;

        if ($status === 401) {
            return [
                [
                    'label' => $isAdmin ? __('Masuk sebagai Admin') : __('Masuk ke Akun'),
                    'url' => $isAdmin ? '/admin/signin' : '/signin',
                    'icon' => self::ICON_ARROW_RIGHT,
                    'variant' => 'primary',
                ],
            ];
        }

        return [
            [
                'label' => __('Coba Lagi'),
                'url' => null,
                'reload' => true,
                'icon' => self::ICON_RETRY,
                'variant' => 'primary',
            ],
        ];
    }

    /**
     * Ubah warna hex menjadi triplet RGB ("245 158 11") supaya bisa
     * dipakai di `rgb(var(--x) / alpha)`.
     *
     * PEMISAHNYA SPASI, BUKAN KOMA. Ini bukan soal gaya:
     *
     *   - `rgb(245, 158, 11 / 0.5)` TIDAK VALID. Sintaks `rgb()` modern
     *     (yang mendukung alpha dengan `/`) hanya menerima komponen yang
     *     dipisah SPASI. Yang berkoma hanya boleh di `rgb()` legacy --
     *     tanpa alpha.
     *   - CSS yang tidak valid DIABAIKAN SEHENAPNYA: deklarasi itu
     *     dibuang tanpa pesan apa pun. Efeknya: orb, aurora, gradasi
     *     latar, dan glow tombol HILANG TOTAL, sementara halaman tetap
     *     tampil -- jadi gejalanya "warnanya tidak ada", bukan "error".
     *
     * Karena tripletnya dipisah spasi, pemakaiannya harus:
     *
     *     rgb(var(--gx-accent-rgb-1) / 0.62)
     */
    public static function rgb(string $hex): string
    {
        $hex = ltrim(trim($hex), '#');

        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            return '99 102 241';
        }

        return implode(' ', [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ]);
    }
}
