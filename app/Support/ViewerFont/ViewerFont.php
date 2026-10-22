<?php

namespace App\Support\ViewerFont;

use App\Support\AppFont\AppFont;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;

/**
 * Pilihan huruf MILIK PENGUNJUNG (user / tamu) untuk storefront.
 *
 * Kenapa kelas ini terpisah dari `AppFont`:
 *
 *   - `AppFont` = pengaturan GLOBAL yang diatur admin (tabel
 *     `fm_app_settings`, key `appearance`). Isinya berlaku untuk semua
 *     pengunjung, dan dipakai juga panel admin lewat `admin_family`.
 *   - `ViewerFont` = pilihan PRIBADI satu pengunjung: user yang sudah login
 *     menyimpannya di `users.font_preference` (ikut ke perangkat lain),
 *     tamu menyimpannya di session (ikut selama sesinya hidup).
 *
 * Effective storefront font (yang benar-benar dipakai CSS):
 *
 *   1. session  -> pilihan terakhir di perangkat ini (guest & user)
 *   2. users.font_preference -> pilihan yang tersimpan di akun
 *   3. AppFont::family() -> default yang diatur admin
 *
 * Nilai selalu divalidasi terhadap katalog aset lokal
 * (`AppFont::families()`): nilainya masuk ke CSS sebagai
 * `--app-font-family`, jadifamili yang tidak dikenal harus JATUH, bukan
 * ditulis mentah ke stylesheet.
 *
 * Berlaku untuk panel Welcome (storefront = website + Dekstop App) -- panel
 * admin memakai `AppFont::adminFamily()` yang terpisah, jadi admin yang
 * menune panel kerjanya tidak mengubah tampilan pembeli.
 */
class ViewerFont
{
    /** Kunci session untuk pengunjung yang belum login. */
    public const SESSION_KEY = 'viewer_font_family';

    /** Daftar keluarga yang boleh dipilih (dari aset font lokal). */
    public static function families(): array
    {
        return AppFont::families();
    }

    /**
     * Keluarga huruf yang sedang dipakai pengunjung ini.
     */
    public static function current(): string
    {
        $families = self::families();

        $session = self::sessionValue();
        if ($session !== null && in_array($session, $families, true)) {
            return $session;
        }

        $stored = self::userValue();
        if ($stored !== null && in_array($stored, $families, true)) {
            // Simpan balik ke session supaya permintaan berikutnya (dan
            // render hook CSS) tidak perlu menyentuh database lagi.
            self::putSession($stored);

            return $stored;
        }

        return AppFont::family();
    }

    /**
     * Simpan pilihan pengunjung.
     *
     * Return false kalau nilainya tidak dikenal -- pemanggil harus
     * membatalkan, bukan diam-diam menulis nilai asing ke CSS.
     */
    public static function set(?string $family): bool
    {
        $family = trim((string) $family);

        if ($family === '' || ! in_array($family, self::families(), true)) {
            return false;
        }

        self::putSession($family);

        $user = self::user();
        if ($user !== null) {
            $user->forceFill(['font_preference' => $family])->save();
        }

        return true;
    }

    /**
     * Kembalikan ke default admin (hapus pilihan pribadi).
     */
    public static function reset(): void
    {
        self::forgetSession();

        $user = self::user();
        if ($user !== null && $user->font_preference) {
            $user->forceFill(['font_preference' => null])->save();
        }
    }

    /**
     * True kalau pengunjung ini punya pilihan pribadi (bukan default admin).
     */
    public static function hasCustom(): bool
    {
        $current = self::current();

        return $current !== AppFont::family();
    }

    // ── Internals ───────────────────────────────────────────────────────────

    private static function user(): ?Authenticatable
    {
        $user = Auth::user();

        return $user instanceof Authenticatable ? $user : null;
    }

    private static function sessionValue(): ?string
    {
        if (! app()->bound('session') || ! app('session')->isStarted()) {
            return null;
        }

        $value = session(self::SESSION_KEY);

        return is_string($value) ? $value : null;
    }

    private static function putSession(string $family): void
    {
        if (! app()->bound('session') || ! app('session')->isStarted()) {
            return;
        }

        session()->put(self::SESSION_KEY, $family);
    }

    private static function forgetSession(): void
    {
        if (! app()->bound('session') || ! app('session')->isStarted()) {
            return;
        }

        session()->forget(self::SESSION_KEY);
    }

    private static function userValue(): ?string
    {
        $user = self::user();

        if ($user === null) {
            return null;
        }

        $value = $user->getAttribute('font_preference');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
