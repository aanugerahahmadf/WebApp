<?php

namespace App\Services\AutoTranslationService;

use App\Models\Translation\Translation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AutoTranslationService
{
    /**
     * Locale yang tidak perlu otomatis diterjemahkan.
     */
    protected array $skipLocales = ['id'];

    /**
     * Pemetaan locale Laravel → kode bahasa MyMemory API.
     */
    protected array $localeMap = [
        'id' => 'id',
        'en' => 'en-GB',
        'en_US' => 'en-US',
        'ar' => 'ar',
        'de' => 'de',
        'fr' => 'fr',
        'es' => 'es',
        'it' => 'it',
        'ja' => 'ja',
        'ko' => 'ko',
        'zh' => 'zh-CN',
        'zh_CN' => 'zh-CN',
        'zh_TW' => 'zh-TW',
        'ru' => 'ru',
        'tr' => 'tr',
        'hi' => 'hi',
        'nl' => 'nl',
        'pt' => 'pt',
        'pt_BR' => 'pt',
        'pt_PT' => 'pt-PT',
        'vi' => 'vi',
        'th' => 'th',
        'ms' => 'ms',
        'fa' => 'fa',
        'ur' => 'ur',
        'bn' => 'bn',
        'fil' => 'tl',
        'pl' => 'pl',
        'uk' => 'uk',
        'ro' => 'ro',
        'cs' => 'cs',
        'hu' => 'hu',
        'el' => 'el',
        'sv' => 'sv',
        'da' => 'da',
        'fi' => 'fi',
        'no' => 'nb',
        'hr' => 'hr',
        'sk' => 'sk',
        'bg' => 'bg',
        'lt' => 'lt',
        'lv' => 'lv',
        'et' => 'et',
        'sr' => 'sr',
        'he' => 'he',
        'sw' => 'sw',
        'my' => 'my',
        'am' => 'am',
        'az' => 'az',
        'bs' => 'bs',
        'ca' => 'ca',
        'ckb' => 'ku',
        'cy' => 'cy',
        'eu' => 'eu',
        'ka' => 'ka',
        'kk' => 'kk',
        'km' => 'km',
        'ku' => 'ku',
        'mn' => 'mn',
        'ne' => 'ne',
        'np' => 'ne',
        'sl' => 'sl',
        'sq' => 'sq',
        'tl' => 'tl',
        'uz' => 'uz',
        'zu' => 'zu',
    ];

    /**
     * Budget waktu maksimal API per request (detik).
     */
    protected float $maxApiTimePerRequest = 3.0;

    protected float $apiTimeSpent = 0.0;

    protected int $maxApiCallsPerRequest = 8;

    protected int $apiCallCount = 0;

    /**
     * Cache terjemahan aktif di memory (Static agar awet di NativePHP/Octane).
     */
    protected static array $activeMap = [];

    protected static ?string $activeLocale = null;

    /**
     * Daftar label yang harus diabaikan (seperti nama bahasa di switcher).
     */
    protected ?array $ignoredLabels = null;

    /**
     * Terjemahkan teks mendalam dengan sistem sinkron otomatis + optimasi 0,01ms.
     */
    public function translate(string $text, string $targetLocale): string
    {
        $cleanText = trim($text);

        // 1. Filter dasar
        if ($targetLocale === 'id' || empty($cleanText) || is_numeric($cleanText)) {
            return $text;
        }

        // 2. Jangan terjemahkan Nama Bahasa (English, Indonesian, etc) agar tidak jadi "friend request"
        if ($this->shouldIgnore($cleanText)) {
            return $text;
        }

        // --- CEK KONDISI BOOT / CLI / DB ---
        // Jangan paksa DB/Cache jika sedang discovery atau DB tidak reachable (seperti saat build Laravel Cloud)
        try {
            if (app()->runningInConsole()) {
                $args = implode(' ', $_SERVER['argv'] ?? []);
                if (str_contains($args, 'package:discover') || str_contains($args, 'dump-autoload')) {
                    return $text;
                }
            }
            \DB::connection()->getPdo();
        } catch (\Throwable $e) {
            return $text; // FAIL SAFE
        }

        // 3. Prevent DB/Cache access during boot/console commands that don't need it (like package:discover)
        if (app()->runningInConsole() && ! app()->bound('translator')) {
            return $text;
        }

        // 4. Load Mapping (0,01ms Strategy)
        if (self::$activeLocale !== $targetLocale) {
            $cacheKey = "active_trans_map_{$targetLocale}";

            try {
                self::$activeMap = Cache::get($cacheKey, []);

                if (empty(self::$activeMap)) {
                    self::$activeMap = Translation::query()->whereTargetLocale($targetLocale)
                        ->pluck('translated_text', 'source_text')
                        ->toArray();
                    Cache::put($cacheKey, self::$activeMap, now()->addHours(24));
                }
            } catch (\Throwable $e) {
                // Return original text if DB/Cache is not ready
                return $text;
            }
            self::$activeLocale = $targetLocale;
        }

        // 4. Lookup Instan Memory
        if (isset(self::$activeMap[$text])) {
            return self::$activeMap[$text];
        }

        // 5. Sinkronisasi API dengan Budgeting (Cegah Timeout)
        if (strlen($text) > 400 || $this->apiCallCount >= $this->maxApiCallsPerRequest || $this->apiTimeSpent >= $this->maxApiTimePerRequest) {
            return $text;
        }

        $this->apiCallCount++;
        $startTime = microtime(true);
        $targetLang = $this->localeMap[$targetLocale] ?? $targetLocale;

        $translated = $this->callApi($cleanText, $targetLang);
        $this->apiTimeSpent += (microtime(true) - $startTime);

        if ($translated !== $cleanText && ! empty($translated)) {
            try {
                // Simpan permanen
                Translation::updateOrCreate(
                    ['source_hash' => md5($text), 'target_locale' => $targetLocale],
                    ['source_text' => $text, 'translated_text' => $translated]
                );

                // Update local memory map
                self::$activeMap[$text] = $translated;

                // Update Cache Global per-product tanpa menghapus yang sudah ada
                $cacheKey = "active_trans_map_{$targetLocale}";
                try {
                    $currentCache = Cache::get($cacheKey, []);
                    $currentCache[$text] = $translated;
                    Cache::put($cacheKey, $currentCache, now()->addHours(24));
                } catch (\Throwable $e) {
                }
            } catch (\Throwable $e) {
            }
        }

        return $translated;
    }

    /**
     * Abaikan kata-kata navigasi atau bahasa di switcher.
     */
    protected function shouldIgnore(string $text): bool
    {
        if ($this->ignoredLabels === null) {
            $this->ignoredLabels = collect(config('filament-language-switcher.locals', []))
                ->pluck('label')
                ->map(fn ($l) => trim($l))
                ->toArray();

            // Tambahkan label navigasi sensitif lainnya
            $this->ignoredLabels[] = 'Indonesian';
            $this->ignoredLabels[] = 'English (US)';
            $this->ignoredLabels[] = 'English (UK)';
            $this->ignoredLabels[] = 'Arabic';
        }

        return in_array($text, $this->ignoredLabels);
    }

    /**
     * Request ke MyMemory API dengan perlindungan anti-sampah.
     */
    protected function callApi(string $text, string $targetLang): string
    {
        try {
            $response = Http::timeout(2)
                ->withoutVerifying()
                ->withUserAgent('Mozilla/5.0')
                ->get('https://api.mymemory.translated.net/get', [
                    'q' => $text,
                    'langpair' => "id|{$targetLang}",
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $result = $data['responseData']['translatedText'] ?? null;

                if ($result && ($data['responseStatus'] ?? 0) == 200) {
                    $result = html_entity_decode(strip_tags($result), ENT_QUOTES | ENT_HTML5, 'UTF-8');

                    // VALIDASI: Jika hasil berisi teks sampah/warning dari MyMemory, abaikan.
                    $trash = ['QUERY SPECIFIED', 'MYMEMORY WARNING', 'FRIEND REQUEST', 'API LIMIT', 'PLEASE WAIT'];
                    foreach ($trash as $word) {
                        if (str_contains(strtoupper($result), $word)) {
                            return $text;
                        }
                    }

                    // Jika hasil terlalu jauh beda panjangnya (> 3x), kemungkinan sampah
                    if (strlen($result) > strlen($text) * 4) {
                        return $text;
                    }

                    return $result;
                }
            }
        } catch (\Throwable $e) {
        }

        return $text;
    }

    public function shouldSkip(string $locale): bool
    {
        return in_array($locale, $this->skipLocales);
    }
}
