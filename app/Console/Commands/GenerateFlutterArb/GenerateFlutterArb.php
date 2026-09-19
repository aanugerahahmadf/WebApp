<?php

namespace App\Console\Commands\GenerateFlutterArb;

use App\Models\Translation\Translation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Stichoza\GoogleTranslate\GoogleTranslate;

class GenerateFlutterArb extends Command
{
    protected $signature = 'lang:generate-flutter-arb {flutterPath? : Path ke folder l10n Flutter} {--translate : Terjemahkan semua key via Google Translate}';

    protected $description = 'Generate .arb files for all locales from Flutter app_id.arb source';

    private array $_translationCache = [];

    private function getFlutterPath(): string
    {
        return $this->argument('flutterPath')
            ?? base_path('../mobile_app/lib/l10n');
    }

    private function getLocaleMap(): array
    {
        return [
            'id' => 'id', 'en' => 'en', 'en_US' => 'en',
            'ms' => 'ms', 'zh' => 'zh-CN', 'zh_CN' => 'zh-CN', 'zh_TW' => 'zh-TW',
            'ar' => 'ar', 'ja' => 'ja', 'ko' => 'ko', 'th' => 'th', 'vi' => 'vi',
            'hi' => 'hi', 'bn' => 'bn', 'ur' => 'ur', 'fa' => 'fa',
            'pt' => 'pt', 'pt_BR' => 'pt', 'pt_PT' => 'pt-PT',
            'es' => 'es', 'fr' => 'fr', 'de' => 'de', 'it' => 'it',
            'nl' => 'nl', 'ru' => 'ru', 'tr' => 'tr', 'pl' => 'pl',
            'uk' => 'uk', 'ro' => 'ro', 'cs' => 'cs', 'hu' => 'hu',
            'el' => 'el', 'sv' => 'sv', 'da' => 'da', 'fi' => 'fi',
            'no' => 'nb', 'fil' => 'tl', 'my' => 'my', 'km' => 'km',
            'he' => 'he', 'sr' => 'sr', 'hr' => 'hr', 'sk' => 'sk',
            'bg' => 'bg', 'lt' => 'lt', 'lv' => 'lv', 'et' => 'et',
            'sl' => 'sl', 'sq' => 'sq', 'bs' => 'bs', 'hy' => 'hy',
            'ka' => 'ka', 'az' => 'az', 'kk' => 'kk', 'mn' => 'mn',
            'ne' => 'ne', 'np' => 'ne', 'tl' => 'tl', 'sw' => 'sw',
            'am' => 'am', 'ca' => 'ca', 'eu' => 'eu', 'cy' => 'cy',
            'uz' => 'uz', 'ku' => 'ku', 'ckb' => 'ku', 'zu' => 'zu',
        ];
    }

    public function handle(): int
    {
        $flutterPath = $this->getFlutterPath();
        $sourceFile = $flutterPath.'/app_id.arb';
        $enFile = $flutterPath.'/app_en.arb';

        if (!File::exists($sourceFile)) {
            $this->error("File sumber tidak ditemukan: $sourceFile");
            return 1;
        }

        $this->info("📁 Flutter l10n path: $flutterPath");
        $idTranslations = json_decode(File::get($sourceFile), true);
        $enTranslations = File::exists($enFile) ? json_decode(File::get($enFile), true) : [];

        // Filter only value keys (not metadata @keys)
        $sourceKeys = array_filter(array_keys($idTranslations), fn ($k) => !str_starts_with($k, '@'));
        $sourceValues = array_intersect_key($idTranslations, array_flip($sourceKeys));

        $locales = array_keys(config('filament-language-switcher.locals', ['id' => [], 'en' => []]));

        foreach ($locales as $locale) {
            if ($locale === 'id') {
                continue;
            }

            $this->info("\n🌍 $locale");

            $targetFile = $flutterPath.'/app_'.$locale.'.arb';
            $existing = File::exists($targetFile) ? json_decode(File::get($targetFile), true) : [];
            $existingKeys = !empty($existing)
                ? array_filter(array_keys($existing), fn ($k) => !str_starts_with($k, '@'))
                : [];

            // Determine which keys need translation
            $needsTranslate = [];
            foreach ($sourceKeys as $key) {
                $currentValue = $existing[$key] ?? null;
                $idValue = $sourceValues[$key];
                if (!isset($currentValue) || $currentValue === $idValue) {
                    $needsTranslate[$key] = $idValue;
                }
            }

            $output = ['@@locale' => $locale];
            $translated = 0;

            if ($this->option('translate')) {
                $bar = $this->output->createProgressBar(count($needsTranslate));
                $bar->start();

                foreach ($sourceKeys as $key) {
                    $idValue = $sourceValues[$key];
                    if (isset($needsTranslate[$key])) {
                        $gCode = $this->getLocaleMap()[$locale] ?? $locale;
                        $output[$key] = $this->translateViaCache($idValue, $locale, $gCode);
                        $translated++;
                    } else {
                        $output[$key] = $existing[$key];
                    }
                    $metaKey = '@'.$key;
                    if (isset($idTranslations[$metaKey])) {
                        $output[$metaKey] = $idTranslations[$metaKey];
                    }
                    $bar?->advance();
                }
                $bar?->finish();
                $this->line('');
            } else {
                // Fallback: use English value if exists, else Indonesian
                foreach ($sourceKeys as $key) {
                    if (isset($existing[$key])) {
                        $output[$key] = $existing[$key];
                    } elseif (isset($enTranslations[$key])) {
                        $output[$key] = $enTranslations[$key];
                    } else {
                        $output[$key] = $sourceValues[$key];
                    }
                    $metaKey = '@'.$key;
                    if (isset($idTranslations[$metaKey])) {
                        $output[$metaKey] = $idTranslations[$metaKey];
                    }
                }
            }

            ksort($output);
            File::put($targetFile, json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            $this->info("   ✅ app_$locale.arb (".count($output)." keys, $translated baru)");
        }

        $this->info("\n🏁 Semua .arb file selesai!");
        $this->info("➡️  Jalankan: cd mobile_app && flutter gen-l10n");
        $this->info("➡️  Atau untuk translate via Google: php artisan lang:generate-flutter-arb --translate");
        return 0;
    }

    private function translateViaCache(string $text, string $locale, string $gCode): string
    {
        $cacheKey = md5($text).'_'.$locale;
        if (isset($this->_translationCache[$cacheKey])) {
            return $this->_translationCache[$cacheKey];
        }

        try {
            $dbResult = Translation::where('source_hash', md5($text))
                ->where('target_locale', $locale)
                ->value('translated_text');
            if ($dbResult) {
                $this->_translationCache[$cacheKey] = $dbResult;
                return $dbResult;
            }
        } catch (\Throwable $e) {
        }

        try {
            // Preserve ICU placeholders like {name}, {count}, {date} from translation
            $placeholderPattern = '/\{[a-zA-Z_]+[a-zA-Z0-9_]*\}/';
            preg_match_all($placeholderPattern, $text, $matches);
            $placeholders = $matches[0] ?? [];
            $replacedKeys = [];
            $replaced = $text;
            foreach ($placeholders as $i => $ph) {
                $key = "\x00PH".$i."\x00";
                $replaced = str_replace($ph, $key, $replaced);
                $replacedKeys[$key] = $ph;
            }

            $tr = new GoogleTranslate($gCode);
            $tr->setSource('id');
            $translated = $tr->translate($replaced) ?: $replaced;

            // Restore placeholders (case-insensitive in case Google changed casing)
            $result = $translated;
            foreach ($replacedKeys as $key => $original) {
                $result = str_ireplace($key, $original, $result);
            }
        } catch (\Throwable $e) {
            $result = $text;
        }

        try {
            Translation::updateOrCreate(
                ['source_hash' => md5($text), 'target_locale' => $locale],
                ['source_text' => $text, 'translated_text' => $result]
            );
        } catch (\Throwable $e) {
        }

        $this->_translationCache[$cacheKey] = $result;
        return $result;
    }
}
