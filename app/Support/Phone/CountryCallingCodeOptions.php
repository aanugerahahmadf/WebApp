<?php

namespace App\Support\Phone;

use libphonenumber\CountryCodeToRegionCodeMap;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

final class CountryCallingCodeOptions
{
    /**
     * Daftar pilihan untuk Select bawaan Filament. Nilai menyimpan kode dial
     * dan ISO agar negara yang berbagi kode (mis. +1) tetap dapat dipilih.
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
            $options[$selection] = sprintf(
                '<span class="country-iso-code" style="font-size: 9px !important; font-weight: 800; letter-spacing: .02em; vertical-align: 1px;">%s</span> %s (%s)',
                $region,
                e($country),
                $dialCode,
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
