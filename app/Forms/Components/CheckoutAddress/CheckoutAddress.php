<?php

namespace App\Forms\Components\CheckoutAddress;

use Closure;
use Filament\Forms\Components\Field;
use Illuminate\Support\HtmlString;

/**
 * Pemilih alamat acara di checkout (Produk & Paket), dengan GPS dan Google
 * Places.
 *
 * Menggantikan `Textarea::make('notes')` yang dulu jadi satu-satunya tempat
 * alamat acara diisi. Textarea itu punya dua masalah: tidak ada koordinat
 * (tim dekorasi tidak tahu di mana acara berlangsung), dan tidak ada cara
 * mengisi cepat dari lokasi saat ini.
 *
 * Bentuk state (satu array, dehydrated ke `notes` sebagai teks lengkap):
 *
 *     administrative_area  "Provinsi, Kota, Kecamatan, Kode Pos"
 *     street              "Nama Jalan, Gedung, No. Rumah"
 *     detail              "Detail lain (blok/unit, patokan)"
 *     label               'home' | 'office'  -- "Tandai sebagai"
 *     latitude / longitude, place_id
 *     formatted           tidak disimpan sebagai key: teks akhir selalu dihitung
 *                       ulang di server lewat formatAddress() saat state dibaca.
 *
 * Teks alamat akhir dihitung DI SERVER (lihat formatAddress()), bukan di browser:
 * string yang dikirim ke `Order::create(['notes' => ...])` harus berasal dari
 * kode yang sama dengan yang melapisi halaman ini. Kalau dirakit di JavaScript,
 * isinya bisa berbeda dari yang tampil (mis. urutan baris berubah) dan tidak ada
 * test yang bisa memegangnya.
 *
 * Ber degrade dengan aman ketika `GOOGLE_MAPS_API_KEY` kosong: peta dan
 * autocomplete dinonaktifkan, tetapi tombol GPS, pengetikan manual, dan tombol
 * simpan tetap berfungsi. Alamat tidak boleh hilang hanya karena quota Google
 * habis atau key belum diisi.
 *
 * Field ini TIDAK menyimpan apa pun ke profil user. Screenshot acuan punya
 * centang "Jangan simpan untuk Owner/Pembeli", jadi defaultnya justru tidak
 * disimpan: memindahkan keputusan itu ke luarlingkup checkout.
 */
class CheckoutAddress extends Field
{
    protected string $view = 'User.components.checkout-address.checkout-address';

    protected string|Closure|null $mapsKey = null;

    /**
     * Key Google Maps bisa dioverride per pemakaian (mis. uji coba dengan key
     * lain) tanpa menyentuh config.
     */
    public function mapsKey(string|Closure|null $key): static
    {
        $this->mapsKey = $key;

        return $this;
    }

    public function getMapsKey(): string
    {
        $key = $this->evaluate($this->mapsKey);

        if (filled($key)) {
            return (string) $key;
        }

        return (string) config('services.google.maps_key', '');
    }

    public function hasMapsKey(): bool
    {
        return filled($this->getMapsKey());
    }

    /**
     * Bentuk default state, dipakai view dan dehydrateStateUsing.
     *
     * @return array<string, mixed>
     */
    public static function emptyState(): array
    {
        return [
            'administrative_area' => null,
            'street' => null,
            'detail' => null,
            'label' => 'home',
            'save_for_user' => false,
            'place_id' => null,
            'latitude' => null,
            'longitude' => null,
            'formatted' => null,
        ];
    }

    /**
     * Bentuk state yang dipakai view dan `handleCheckout()`.
     *
     * String (state lama dari `Textarea::make('notes')`, atau draft yang dibuat
     * sebelum field ini ada) dipetakan ke `street`, bukan dibuang: alamat yang
     * sudah diketik pengguna tidak boleh hilang begitu form dibuka lagi.
     *
     * @return array<string, mixed>
     */
    public static function normalize(mixed $state): array
    {
        if (is_string($state)) {
            $state = ['street' => $state];
        }

        $state = is_array($state) ? $state : [];

        return array_merge(static::emptyState(), array_filter(
            $state,
            fn ($value): bool => $value !== null && $value !== '',
        ));
    }

    /**
     * Satu-satunya representasi alamat yang sampai ke `orders.notes`.
     *
     * Urutannya disengaja dan tetap: baris paling kasar lebih dulu, detail
     * paling spesifik di akhir, supaya orang yang membacakan alamat menemukan lokasi
     * besar lebih dulu. Baris kosong tidak pernah ikut (alamat tanpa kota saja
     * tidak berguna).
     */
    public static function formatAddress(mixed $state): string
    {
        $state = static::normalize($state);

        $lines = array_filter([
            $state['detail'],
            $state['street'],
            $state['administrative_area'],
        ], fn ($line): bool => filled(is_string($line) ? trim($line) : $line));

        return trim(implode("\n", $lines));
    }

    /**
     * Ringkasan satu baris untuk ditampilkan saat modal tertutup.
     */
    public function getSummary(): ?string
    {
        $state = $this->getState();

        if (! is_array($state)) {
            return filled($state) ? (string) $state : null;
        }

        $formatted = $state['formatted'] ?? static::formatAddress($state);

        return filled($formatted) ? (string) $formatted : null;
    }

    /**
     * Label "Tandai sebagai" yang tampil di ringkasan.
     */
    public function getLabelText(): HtmlString|string
    {
        $state = $this->getState();
        $label = is_array($state) ? ($state['label'] ?? null) : null;

        return match ($label) {
            'office' => __('Kantor'),
            default => __('Rumah'),
        };
    }
}
