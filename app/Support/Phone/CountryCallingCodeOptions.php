<?php

namespace App\Support\Phone;

use libphonenumber\CountryCodeToRegionCodeMap;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

final class CountryCallingCodeOptions
{
    /**
     * Bendera emoji dari kode ISO 3166-1 alpha-2 (mis. "ID" menjadi flag
     * Indonesia).
     *
     * Dibangun dari regional indicator symbol: tiap huruf A-Z dipetakan ke
     * U+1F1E6 + offset. Dipilih daripada gambar eksternal (flagcdn.com dsb)
     * karena tidak butuh koneksi -- penting untuk shell native Capacitor dan
     * untuk halaman auth yang harus tampil sebelum ada akses internet.
     *
     * Mengembalikan string kosong kalau kodenya bukan dua huruf, supaya
     * pemanggil bisa jatuh ke kode ISO sebagai teks biasa.
     */
    public static function flag(string $region): string
    {
        $letters = strtoupper((string) preg_replace('/[^A-Za-z]/', '', $region));

        if (strlen($letters) !== 2) {
            return '';
        }

        return implode('', array_map(
            static fn (string $letter): string => mb_chr(0x1F1E6 + (ord($letter) - ord('A')), 'UTF-8'),
            str_split($letters)
        ));
    }

    /**
     * Daftar pilihan untuk Select bawaan Filament. Nilai menyimpan kode dial
     * dan ISO agar negara yang berbagi kode (mis. +1) tetap dapat dipilih.
     *
     * Label memakai HTML (Select wajib `->allowHtml()`): nama negara dan kode
     * dial di-escape, sedangkan bendera emoji tidak perlu.
     *
     * @return array<string, string>
     */
    public static function all(): array
    {
        $countries = [];

        foreach (CountryCodeToRegionCodeMap::COUNTRY_CODE_TO_REGION_CODE_MAP as $dialCode => $regions) {
            foreach ($regions as $region) {
                if ($region === '001') {
                    continue;
                }

                $selection = sprintf('+%s|%s', $dialCode, $region);
                $countries[$selection] = \Locale::getDisplayRegion('und_'.$region, 'en');
            }
        }

        asort($countries, SORT_NATURAL | SORT_FLAG_CASE);

        $options = [];

        foreach ($countries as $selection => $country) {
            [$dialCode, $region] = explode('|', $selection);
            $flag = self::flag($region);

            // Fallback ke kode ISO sebagai teks untuk platform yang tidak
            // merender flag emoji (webview lama, sebagian font Linux).
            $marker = $flag !== ''
                ? sprintf('<span class="country-flag" title="%s" aria-label="%s">%s</span>', e($region), e($region), $flag)
                : sprintf('<span class="country-iso-code">%s</span>', e($region));

            $options[$selection] = sprintf(
                '%s <span class="country-name">%s</span> <span class="country-dial-code">(%s)</span>',
                $marker,
                e($country),
                e($dialCode),
            );
        }

        return $options;
    }

    public static function defaultSelection(): string
    {
        return '+62|ID';
    }

    /** @return array{selection: string, national: string} */
    public static function split(?string $phone): array
    {
        $digits = preg_replace('/\D/', '', (string) $phone);

        if ($digits === '') {
            return ['selection' => self::defaultSelection(), 'national' => ''];
        }

        foreach (array_keys(CountryCodeToRegionCodeMap::COUNTRY_CODE_TO_REGION_CODE_MAP) as $dialCode) {
            $code = (string) $dialCode;

            if (! str_starts_with($digits, $code)) {
                continue;
            }

            $region = CountryCodeToRegionCodeMap::COUNTRY_CODE_TO_REGION_CODE_MAP[$dialCode][0] ?? 'ID';

            return [
                'selection' => sprintf('+%s|%s', $code, $region),
                'national' => substr($digits, strlen($code)),
            ];
        }

        // Nomor lama tanpa kode negara diperlakukan sebagai nomor Indonesia.
        return ['selection' => self::defaultSelection(), 'national' => ltrim($digits, '0')];
    }

    public static function toE164(?string $selection, ?string $nationalNumber): string
    {
        $nationalNumber = preg_replace('/\D/', '', (string) $nationalNumber);

        if ($nationalNumber === '') {
            return '';
        }

        [$dialCode, $region] = explode('|', $selection ?: self::defaultSelection());

        try {
            return PhoneNumberUtil::getInstance()->format(
                PhoneNumberUtil::getInstance()->parse($nationalNumber, $region),
                PhoneNumberFormat::E164,
            );
        } catch (\Throwable) {
            // Pertahankan input sebagai E.164 meskipun nomor masih belum lengkap.
        }

        return $dialCode.ltrim($nationalNumber, '0');
    }

}
