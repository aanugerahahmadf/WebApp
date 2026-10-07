<?php

namespace App\Filament\Shared\Concerns\HandlesTwoFactorAuthenticator;

use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use PragmaRX\Google2FAQRCode\Google2FA as Google2FAQRCode;
use PragmaRX\Google2FAQRCode\QRCode\Chillerlan;

/**
 * Tampilan setup Authenticator App, dipakai dua halaman.
 *
 *   1. Auth\TwoFactorAuth                 -- diminishing "strengthen your account?"
 *   2. …\SignInAndRecovery\TwoFactorySetup -- tombol "Tambah Aplikasi
 *      Autentikasi" di Pengaturan
 *
 * Dipisah karena keduanya memang terpisah: yang pertama hanya untuk aktivasi
 * sukarela di tengah alur login, yang kedua untuk mengelola keamanan dari
 * Pengaturan. Yang sama adalah tampilannya -- QR code dan Kode Manual --
 * sehingga ditulis sekali di sini. Kalau dipisah, keduanya pasti akan
 * berbeda begitu salah satu diubah.
 *
 * Isi trait ini hanya presentation plus pembacaan secret. Aturan verifikasi
 * TOTP dan pembuatan kode cadangan milik paket mix-code/filament-multi-2fa
 * dan tidak dicopy: menyalinnya berarti setiap perbaikan harus diterapkan dua
 * kali.
 */
trait HandlesTwoFactorAuthenticator
{
    /**
     * @return array<int, string>
     */
    protected function getInfolists(): array
    {
        return [
            'setupDetails',
        ];
    }

    /**
     * QR code + Kode Manual, berurutan.
     *
     * Infolist, bukan field form, karena nilai secret baru ada setelah
     * pengguna menekan tombol lanjut -- sedangkan state form sudah terisi
     * lebih dulu: Livewire mengisi form pada tahap hidrasi, sebelum method
     * yang menGenerate secret jalan. Field form apa pun (default(),
     * afterStateHydrated(), formatStateUsing()) akan membaca secret yang
     * masih null dan menampilkan kotak kosong, padahal QR-nya sudah benar.
     *
     * Entry milik infolist dievaluasi saat render -- jauh setelah secret ada.
     */
    public function setupDetails(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                TextEntry::make('qr_code')
                    ->hiddenLabel()
                    // Sengaja TIDAK memakai ->html().
                    //
                    // ->html() membuat Filament menjalankan
                    // Str::sanitizeHtml() pada nilainya, dan sanitizer itu
                    // membuang <svg> beserta isinya. Hasilnya QR hilang tanpa
                    // error apa pun -- halamannya hanya terlihat kosong di
                    // tempat QR seharusnya ada.
                    //
                    // Mengembalikan HtmlString tanpa ->html() melewati
                    // sanitizer, tapi Filament tetap merendernya mentah.
                    ->state(fn (): ?Htmlable => $this->qrCodeHtml()
                        ? new HtmlString($this->qrCodeHtml())
                        : null),

                TextEntry::make('secret_key')
                    ->label(__('Kode Manual'))
                    ->icon('heroicon-m-key')
                    ->state(fn (): ?string => $this->manualSecret())
                    // Tanpa nilai berarti setup belum dimulai; entri
                    // disembunyikan supaya tidak ada kotak kosong yang
                    // membingungkan.
                    ->hidden(fn (): bool => $this->manualSecret() === null),
            ])
            ->columns(1);
    }

    /**
     * Secret key yang sedang aktif, untuk dimasukkan manual.
     *
     * Penting karena QR code saja tidak selalu cukup: kamera bisa tidak ada,
     * layar terlalu kecil, atau aplikasinya ada di perangkat lain. Secret ini
     * adalah isi QR-nya, jadi mengetiknya manual memberi hasil yang sama.
     *
     * Sumbernya $this->user milik paket, bukan auth()->user(). Keduanya
     * seharusnya sama, tapi auth()->user() mengembalikan null di dalam
     * komponen Livewire -- jadi andalkan itu membuat QR dan Kode Manual
     * kosong tanpa error. $this->user justru yang baru saja diisi
     * generateTwoFactorAuthenticatorAppOTPCode(), jadi nilainya yang terbaru.
     */
    public function manualSecret(): ?string
    {
        $secret = $this->user?->two_factor_secret ?? auth()->user()?->two_factor_secret;

        return blank($secret) ? null : (string) $secret;
    }

    /**
     * QR code untuk secret yang sedang aktif.
     *
     * Memakai API dan backend yang sama dengan paket, supaya kode yang dibuat
     * di sini tetap bisa diverifikasi o-t-p-verify.
     */
    public function qrCodeHtml(): ?string
    {
        $secret = $this->manualSecret();

        if ($secret === null) {
            return null;
        }

        $backend = config('filament-multi-2fa.qr_code_backend_service');
        $service = new $backend;

        try {
            $image = (new Google2FAQRCode)
                ->setQrCodeService($service)
                ->getQRCodeInline(
                    (string) config('app.name'),
                    (string) ($this->user?->email ?? auth()->user()?->email),
                    $secret,
                );
        } catch (\Throwable) {
            // QR gagal dirakit (mis. backend SVG tidak tersedia). Kode manual
            // tetap jalan, jadi jangan gagalkan seluruh halaman.
            return null;
        }

        // Penting: backend Chillerlan mengembalikan data URI mentah, bukan
        // markup. Tanpa dibungkus <img>, hasilnya string yang tidak
        // dirender -- halamannya terlihat tanpa QR, padahal tidak ada error.
        // Perilaku ini sama dengan yang dilakukan paket.
        if ($service instanceof Chillerlan) {
            $image = '<img src="'.$image.'" alt="" class="h-auto w-full max-w-xs" />';
        }

        return $image;
    }

    /**
     * Buat secret baru kalau belum ada.
     *
     * Dipanggil sebelum halaman dirender, supaya QR di infolist tidak pernah
     * kosong padahal secret-nya sebenarnya baru dibuat.
     */
    protected function ensureTwoFactorSecret(): void
    {
        if ($this->manualSecret() !== null) {
            return;
        }

        // $this->user lebih dulu, dengan alasan yang sama seperti di
        // manualSecret(): auth()->user() bisa null di dalam komponen Livewire.
        ($this->user ?? auth()->user())?->generateTwoFactorAuthenticatorAppOTPCode();
    }
}
