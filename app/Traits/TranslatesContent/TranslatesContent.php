<?php

namespace App\Traits\TranslatesContent;

use App\Models\Translation\Translation;
use Stichoza\GoogleTranslate\GoogleTranslate;

trait TranslatesContent
{
    private static array $_translationCache = [];

    private static ?array $_supportedLocales = null;

    private static array $_googleInstances = [];

    private function getSupportedLocales(): array
    {
        if (self::$_supportedLocales === null) {
            $locales = array_keys(config('filament-language-switcher.locals', []));
            self::$_supportedLocales = array_values(array_filter($locales, fn ($l) => $l !== 'id'));
        }
        return self::$_supportedLocales;
    }

    private function getGoogleInstance(string $locale): GoogleTranslate
    {
        if (!isset(self::$_googleInstances[$locale])) {
            $gCode = match ($locale) {
                'zh', 'zh_CN' => 'zh-CN',
                'zh_TW' => 'zh-TW',
                'en', 'en_US' => 'en',
                'pt', 'pt_BR' => 'pt',
                'pt_PT' => 'pt-PT',
                'fil' => 'tl',
                'sr' => 'sr',
                'no' => 'nb',
                'ckb' => 'ku',
                default => $locale,
            };
            $tr = new GoogleTranslate($gCode);
            $tr->setSource('id');
            self::$_googleInstances[$locale] = $tr;
        }
        return self::$_googleInstances[$locale];
    }

    private function translateText(string $indonesian, string $locale): string
    {
        $cacheKey = md5($indonesian).'_'.$locale;
        if (isset(self::$_translationCache[$cacheKey])) {
            return self::$_translationCache[$cacheKey];
        }

        try {
            $dbTranslation = Translation::where('source_hash', md5($indonesian))
                ->where('target_locale', $locale)
                ->value('translated_text');
            if ($dbTranslation) {
                self::$_translationCache[$cacheKey] = $dbTranslation;
                return $dbTranslation;
            }
        } catch (\Throwable $e) {
        }

        try {
            $tr = $this->getGoogleInstance($locale);
            $translated = $tr->translate($indonesian);
            $result = $translated ?: $indonesian;
        } catch (\Throwable $e) {
            $result = $indonesian;
        }

        try {
            Translation::updateOrCreate(
                ['source_hash' => md5($indonesian), 'target_locale' => $locale],
                ['source_text' => $indonesian, 'translated_text' => $result]
            );
        } catch (\Throwable $e) {
        }

        self::$_translationCache[$cacheKey] = $result;
        return $result;
    }

    private function translateArray(array $items, string $locale): array
    {
        $result = [];
        foreach ($items as $key => $value) {
            if (is_string($value)) {
                $result[$key] = $this->translateText($value, $locale);
            } elseif (is_array($value)) {
                $result[$key] = $this->translateArray($value, $locale);
            } else {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    private function translateToAllLocales(string $idText, ?string $enText = null): array
    {
        $translations = ['id' => $idText];
        $translations['en'] = $enText ?? $this->translateText($idText, 'en');

        $totalLocales = $this->getSupportedLocales();
        $this->command?->info("  🌍 Menerjemahkan '".mb_substr($idText, 0, 40)."...' ke ".count($totalLocales)." bahasa");

        $bar = $this->command?->getOutput()->createProgressBar(count($totalLocales));
        $bar?->start();

        foreach ($totalLocales as $locale) {
            if ($locale === 'en') {
                $bar?->advance();
                continue;
            }
            $translations[$locale] = $this->translateText($idText, $locale);
            $bar?->advance();
            usleep(20000);
        }

        $bar?->finish();
        $this->command?->line('');

        return $translations;
    }

    private function translateArrayToAllLocales(array $idArray, ?array $enArray = null): array
    {
        $translations = ['id' => $idArray];
        $translations['en'] = $enArray ?? $this->translateArray($idArray, 'en');

        $totalLocales = $this->getSupportedLocales();

        foreach ($totalLocales as $locale) {
            if ($locale === 'en') {
                continue;
            }
            $translations[$locale] = $this->translateArray($idArray, $locale);
        }

        return $translations;
    }
}
