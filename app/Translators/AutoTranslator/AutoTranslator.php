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
