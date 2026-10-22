<?php

namespace App\Translators\AutoTranslator;

use App\Services\AutoTranslationService\AutoTranslationService;
use Illuminate\Translation\Translator;

class AutoTranslator extends Translator
{
    protected ?AutoTranslationService $autoService = null;

    public function setAutoTranslationService(AutoTranslationService $service): void
    {
        $this->autoService = $service;
    }

    public function get($key, array $replace = [], $locale = null, $fallback = true): string|array|null
    {
        $targetLocale = $locale ?? $this->getLocale();

        // 1. PRIORITAS UTAMA: Gunakan Laravel Asli (Cek file JSON/PHP di /lang)
        // Ini memastikan terjemahan yang sudah dikurasi manual selalu menang.
        $translated = parent::get($key, $replace, $targetLocale, $fallback);

        // 1a. Kalau key-nya terdaftar di file terjemahan, nilai itu hasil kurasi
        //     manual --bernilai sama persis dengan key-nya atau tidak.
        //
        //     Kasus ini nyata dan sering: en.json memuat "Sign In" => "Sign In"
        //     karena istilah itu memang tidak perlu diterjemahkan. Versi lama
        //     mendeteksi "belum diterjemahkan" lewat $translated !== $key,
        //     sehingga label yang nilainya identik dengan key dianggap belum
        //     ada -- lalu dikirim ke API terjemahan dan di-cache dengan
        //     varian acak ("Sign In" -> "Log In"). Di en.json ada 552 key
        //     seperti itu, hampir semuanya label UI.
        if ($this->keyExistsInTranslations($key, $targetLocale, $fallback)) {
            return is_string($translated) ? $translated : $key;
        }

        // Jika Laravel berhasil menerjemahkan (hasil != key), langsung kembalikan.
        if ($translated !== $key && ! $this->isUntranslatedPlaceholder($translated, $key)) {
            return $translated;
        }

        // 1b. JSON kadang menyimpan placeholder (nilai = key); ambil dari lang/vendor/*.php
        $vendorTranslation = $this->getVendorNamespacedTranslation($key, $targetLocale, $fallback);
        if ($vendorTranslation !== null) {
            return $this->makeReplacements($vendorTranslation, $replace);
        }

        // 2. FALLBACK: Jika tidak ada di file, gunakan Layanan Auto-Translation
        if ($this->autoService !== null) {
            $autoTranslated = $this->autoService->translate($key, $targetLocale);

            if ($autoTranslated !== $key) {
                return $this->makeReplacements($autoTranslated, $replace);
            }
        }

        return is_string($translated) ? $translated : $key;
    }

    /**
     * Apakah key ini terdaftar di file terjemahan (JSON atau PHP berspaced)?
     *
     * Ini yang dipakai AutoTranslator::get() untuk membedakan:
     *   - "nilainya memang sama dengan key"  -> kurasi manual, jangan diubah
     *   - "key-nya tidak ada sama sekali"      -> boleh auto-terjemahkan
     *
     * Tanpa pemisahan ini, semua label yang engagement-nya kebetulan sama
     * dengan key (mis. "Sign In" => "Sign In") dianggap belum diterjemahkan.
     */
    protected function keyExistsInTranslations(string $key, string $locale, bool $useFallback): bool
    {
        $locales = array_values(array_unique(array_filter([
            $locale,
            $useFallback ? $this->fallback : null,
        ], fn ($value) => is_string($value) && $value !== '')));

        foreach ($locales as $tryLocale) {
            if ($this->jsonKeyExists($key, $tryLocale)) {
                return true;
            }

            if ($this->phpKeyExists($key, $tryLocale)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Cek key di file JSON (group '*', mis. lang/en.json).
     */
    protected function jsonKeyExists(string $key, string $locale): bool
    {
        $this->load('*', '*', $locale);

        $lines = $this->loaded['*']['*'][$locale] ?? [];

        return is_array($lines) && array_key_exists($key, $lines);
    }

    /**
     * Cek key berspaced di file PHP (mis. filament::auth.login).
     */
    protected function phpKeyExists(string $key, string $locale): bool
    {
        if (! str_contains($key, '::')) {
            return false;
        }

        [$namespace, $item] = explode('::', $key, 2);

        $segments = explode('.', $item);
        $group = array_shift($segments);
        $itemKey = implode('.', $segments);

        if ($group === null || $group === '' || $itemKey === '') {
            return false;
        }

        $lines = $this->loader->load($locale, $group, $namespace);

        return is_array($lines) && data_get($lines, $itemKey) !== null;
    }

    /**
     * Detect JSON stub entries that echo the key instead of a real translation.
     */
    protected function isUntranslatedPlaceholder(string|array|null $translated, string $key): bool
    {
        if (! is_string($translated)) {
            return false;
        }

        if ($translated === $key) {
            return true;
        }

        return str_contains($translated, '::') && str_contains($key, '::');
    }

    /**
     * Load namespaced package translations from lang/vendor/{namespace}/{locale}/{group}.php
     */
    protected function getVendorNamespacedTranslation(string $key, string $locale, bool $useFallback): ?string
    {
        if (! str_contains($key, '::')) {
            return null;
        }

        [$namespace, $item] = explode('::', $key, 2);
        $segments = explode('.', $item);
        $group = array_shift($segments);

        if ($group === null || $group === '') {
            return null;
        }

        $itemKey = implode('.', $segments);

        if ($itemKey === '') {
            return null;
        }

        $locales = array_values(array_unique(array_filter([
            $locale,
            $useFallback ? $this->fallback : null,
        ])));

        foreach ($locales as $tryLocale) {
            $lines = $this->loader->load($tryLocale, $group, $namespace);

            if (! is_array($lines)) {
                continue;
            }

            $value = data_get($lines, $itemKey);

            if (is_string($value) && $value !== '' && $value !== $key && ! str_contains($value, '::')) {
                return $value;
            }
        }

        return null;
    }
}
