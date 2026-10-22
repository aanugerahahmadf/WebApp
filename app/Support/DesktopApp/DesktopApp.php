<?php

namespace App\Support\DesktopApp;

use App\Models\Admin\Admin;
use App\Models\User\User;
use App\Support\AppPlatform\AppPlatform;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Sumber tunggal untuk "panel ini sedang berjalan di dalam shell desktop".
 *
 * Ada DUA shell desktop, dan keduanya membungkus server Laravel yang sama
 * di dalam jendela Electron:
 *
 *   - app/Capacitor/DekstopAdminApp  -> panel 'admin'            (Dekorasi Admin)
 *   - app/Capacitor/DekstopApp       -> panel 'welcome' + 'user' (Dekorasi)
 *
 * Karena server-nya sama, panel yang sedang dirender boleh berpindah ke
 * panel lain TANPA keluar dari jendela. Itulah yang dipakai helper ini:
 * panel "saudara" (companion) -- aplikasi desktop lain di server yang sama.
 *
 * Kenapa helper, bukan literal di tiap provider:
 *
 * WelcomePanelProvider dan UserPanelProvider sengaja dijaga sinkron, dan
 * AdminPanelProvider memakai aturan yang sama. Menulis aturan "menu
 * saudara" tiga kali berarti tiga salinan yang bisa berbeda tomorrow --
 * dan caranya gagal senyap: menunya hilang dari satu panel tanpa error.
 *
 * Semua keputusan "apakah ini desktop app?" tetap milik AppPlatform.
 * Kelas ini tidak meng-sniff apa pun sendiri; dia hanya merangkum
 * apa yang sudah diputuskan AppPlatform menjadi keputusan UI.
 */
class DesktopApp
{
    /**
     * Panel id -> nama aplikasi desktop-nya.
     *
     * Dipakai untuk judul jendela (lewat <title>) dan untuk menandai
     * shell mana yang sedang berjalan.
     *
     * @var array<string, string>
     */
    public const APP_NAMES = [
        'admin' => 'Dekorasi Admin',
        'welcome' => 'Dekorasi',
        'user' => 'Dekorasi',
    ];

    /**
     * Panel id -> panel "saudara" di shell desktop yang lain.
     *
     * Panel 'admin' dan panel 'welcome'/+'user' saling menunjuk: dari
     * aplikasi admin kamu bisa membuka toko, dari aplikasi toko kamu bisa
     * membuka panel admin (kalau memang staf).
     *
     * @var array<string, string>
     */
    public const COMPANION_PANELS = [
        'admin' => 'welcome',
        'welcome' => 'admin',
        'user' => 'admin',
    ];

    /**
     * True kalau request ini datang dari shell desktop (Electron).
     *
     * Alias dari AppPlatform::isDesktopApp() -- dipisah supaya kelas ini
     * punya satu pintu masuk untuk semua hal yang spesifik desktop.
     */
    public static function isActive(?Request $request = null): bool
    {
        return AppPlatform::isDesktopApp($request);
    }

    /**
     * Panel yang sedang dirender, atau null di luar request panel.
     */
    public static function currentPanelId(): ?string
    {
        try {
            return Filament::getCurrentPanel()?->getId();
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Nama aplikasi desktop untuk panel tertentu.
     *
     * Lewat `__()` supaya ikut bahasa. Kunci terjemahannya belum ada,
     * jadi untuk sekarang hasilnya sama dengan aslinya ("Dekorasi" /
     * "Dekorasi Admin") -- tapi begitu ada kuncinya, judul jendela desktop
     * (Electron memakai <title>) langsung ikut language switcher.
     *
     * Yang dipakai: awalan <title> di shell desktop, supaya dua jendela
     * (toko & admin) bisa dibedakan di taskbar.
     */
    public static function appName(?string $panelId = null): string
    {
        $panelId ??= static::currentPanelId();

        return __(self::APP_NAMES[$panelId] ?? 'Dekorasi');
    }

    /**
     * Panel saudara, atau null kalau panel ini tidak punya.
     */
    public static function companionPanelId(?string $panelId = null): ?string
    {
        $panelId ??= static::currentPanelId();

        return self::COMPANION_PANELS[$panelId] ?? null;
    }

    /**
     * Label menu untuk panel saudara.
     *
     * Closure-friendly: pemanggilnya tetap harus membungkus dalam closure
     * supaya label ikut berubah kalau locale berganti.
     */
    public static function companionLabel(?string $panelId = null): ?string
    {
        return match (static::companionPanelId($panelId)) {
            'welcome' => __('Buka Toko'),
            'admin' => __('Buka Panel Admin'),
            default => null,
        };
    }

    /**
     * URL panel saudara, atau null kalau tidak ada / tidak bisa dihitung.
     *
     * URL diambil dari halaman Home milik panel tujuan, bukan dari
     * `Panel::getUrl()`: method itu mengembalikan login URL kalau guard
     * panel tujuan belum login, dan untuk link "saudara" kita mau tujuan
     * yang sebenarnya.
     */
    public static function companionUrl(?string $panelId = null): ?string
    {
        return match (static::companionPanelId($panelId)) {
            'welcome' => static::homeUrl(\App\Filament\Welcome\Pages\Home\Home::class, 'welcome'),
            'admin' => static::homeUrl(\App\Filament\Admin\Pages\Home\Home::class, 'admin'),
            default => null,
        };
    }

    /**
     * Apakah orang yang sedang masuk boleh membuka panel admin.
     *
     * Panel admin memakai guard `admin` (tabel `admins`), sedangkan
     * storefront memakai guard `web` (tabel `users`). Satu orang bisa punya
     * baris di keduanya -- itulah yang disebut "storefront identity" di
     * AGENTS.md, dibuat otomatis saat admin masuk lewat kredensial admin.
     *
     * Jadi syaratnya: ada baris `admins` AKTIF yang tertaut ke baris `users`
     * yang sedang login. Tanpa tautan itu, tombol "Buka Panel Admin" akan
     * bouncing ke halaman login admin -- dan di aplikasi desktop itu
     * berarti jendela yang terbuka bukan panel yang dijanjikan.
     */
    public static function canOpenAdminPanel(): bool
    {
        $user = Auth::guard('web')->user();

        if (! $user instanceof User) {
            return false;
        }

        return Admin::query()
            ->where('user_id', $user->getKey())
            ->where('active_status', true)
            ->exists();
    }

    /**
     * URL Home milik sebuah halaman Filament, dengan fallback null.
     */
    protected static function homeUrl(string $pageClass, string $panelId): ?string
    {
        try {
            $url = $pageClass::getUrl(panel: $panelId);

            return is_string($url) && $url !== '' ? $url : null;
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Isi partial shell desktop untuk sebuah hook render.
     *
     * Partial `Shared.components.desktop-shell` yang mendaftarkan hotkey
     * F1..F12 (dan `window.DesktopApp`) harus dirender di DUA hook, karena
     * Filament punya dua bentuk layout:
     *
     *   - layout biasa (dashboard, halaman resource) -> BODY_END
     *   - layout SIMPLE (halaman auth: Sign In, OTP, Complete Profile)
     *     -> SIMPLE_PAGE_END
     *
     * Layout simple TIDAK punya hook BODY_END. Jadi partial yang hanya
     * didaftarkan di BODY_END tidak pernah dirender di halaman auth -- dan
     * hotkey F1..F12 ikut hilang di sana, tanpa error apa pun.
     *
     * Dipakai di ketiga panel supaya tidak ada satu panel yang diam-diam
     * kehilangan hotkey-nya.
     */
    public static function shellPartial(): string
    {
        if (! static::isActive()) {
            return '';
        }

        return view('Shared.components.desktop-shell.desktop-shell')->render();
    }
}
