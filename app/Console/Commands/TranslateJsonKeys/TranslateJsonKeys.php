<?php

namespace App\Console\Commands\TranslateJsonKeys;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslateJsonKeys extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lang:sync-json {--force : Terjemahkan ulang semua key}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sinkronisasi otomatis seluruh text __( ) dari source code ke JSON bahasa (id, en, dll) menggunakan Google Translate.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $langPath = base_path('lang');
        $idFile = $langPath.'/id.json';

        if (! File::exists($idFile)) {
            $this->error('File id.json tidak ditemukan di folder /lang.');

            return;
        }

        $this->info('🔍 Menscan source code untuk mencari label baru (__(...))...');

        $scanPaths = [
            app_path(),
            resource_path('views'),
            config_path(),
            database_path('seeders'),
            base_path('routes'),
        ];

        $foundKeys = [];
        foreach ($scanPaths as $path) {
            if (! File::isDirectory($path) && ! File::exists($path)) {
                continue;
            }

            $filesSrc = File::allFiles($path);
            foreach ($filesSrc as $file) {
                if ($file->getExtension() === 'php' || $file->getExtension() === 'blade.php') {
                    $content = $file->getContents();

                    // 🔍 Regex lebih 'Deep': Mengambil __(), trans(), @lang(), label(), description(), tooltip(), placeholder()
                    $patterns = [
                        "/__\(\s*['\"](.*?)['\"]\s*\)/",
                        "/trans\(\s*['\"](.*?)['\"]\s*\)/",
                        "/@lang\(\s*['\"](.*?)['\"]\s*\)/",
                        "/->label\(\s*['\"](.*?)['\"]\s*\)/",
                        "/->description\(\s*['\"](.*?)['\"]\s*\)/",
                        "/->placeholder\(\s*['\"](.*?)['\"]\s*\)/",
                        "/->tooltip\(\s*['\"](.*?)['\"]\s*\)/",
                        "/->heading\(\s*['\"](.*?)['\"]\s*\)/",
                        "/->title\(\s*['\"](.*?)['\"]\s*\)/",
                        "/->modalHeading\(\s*['\"](.*?)['\"]\s*\)/",
                        "/->modalButton\(\s*['\"](.*?)['\"]\s*\)/",
                        "/->modalDescription\(\s*['\"](.*?)['\"]\s*\)/",
                        "/->modalSubmitActionLabel\(\s*['\"](.*?)['\"]\s*\)/",
                        "/->modalCancelActionLabel\(\s*['\"](.*?)['\"]\s*\)/",
                        "/->emptyStateHeading\(\s*['\"](.*?)['\"]\s*\)/",
                        "/->emptyStateDescription\(\s*['\"](.*?)['\"]\s*\)/",
                        "/->navigationLabel\(\s*['\"](.*?)['\"]\s*\)/",
                        "/->navigationGroup\(\s*['\"](.*?)['\"]\s*\)/",
                        "/->breadcrumb\(\s*['\"](.*?)['\"]\s*\)/",
                        "/->loadingMessage\(\s*['\"](.*?)['\"]\s*\)/",
                    ];

                    foreach ($patterns as $pattern) {
                        preg_match_all($pattern, $content, $matches);
                        if (! empty($matches[1])) {
                            foreach ($matches[1] as $key) {
                                // Abaikan teks teknis, variabel, atau yang kosong
                                if (str_contains($key, '$') || strlen($key) < 1) {
                                    continue;
                                }
                                $foundKeys[$key] = $key;
                            }
                        }
                    }
                }
            }
        }

        $idTranslations = json_decode(File::get($idFile), true) ?? [];
        $addedCount = 0;

        foreach ($foundKeys as $k => $v) {
            if (! isset($idTranslations[$k])) {
                $idTranslations[$k] = $v;
                $addedCount++;
                $this->line(" ✨ Baru: <info>$k</info>");
            }
        }

        if ($addedCount > 0) {
            // Sort keys alphabetically
            ksort($idTranslations);
            File::put($idFile, json_encode($idTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }

        $this->line("🏁 Selesai! id.json diperbarui ($addedCount key baru).");

        // 🟢 BARU: Ambil locales dari config filament-language-switcher
        $configLocals = array_keys(config('filament-language-switcher.locals', []));
        foreach ($configLocals as $local) {
            $localFile = $langPath.'/'.$local.'.json';
            if (! File::exists($localFile)) {
                File::put($localFile, json_encode([], JSON_PRETTY_PRINT));
                $this->info(" ✨ Membuat file bahasa baru: <info>$local.json</info>");
            }
        }

        // 🟢 SINKRONISASI HANYA BAHASA YANG TERDAFTAR DI CONFIG
        $this->info('🌍 Menyelaraskan Bahasa berdasarkan config...');

        foreach ($configLocals as $targetLangCode) {
            if ($targetLangCode === 'id') {
                continue;
            } // id.json sudah diproses di awal

            $file = $langPath.'/'.$targetLangCode.'.json';
            $fileName = $targetLangCode.'.json';

            $this->info('🌍 Menyelaraskan Bahasa: '.strtoupper($targetLangCode).'...');

            if (! File::exists($file)) {
                File::put($file, json_encode([], JSON_PRETTY_PRINT));
            }

            $content = File::get($file);
            $targetTranslations = json_decode($content, true) ?? [];

            // Normalize target lang code for Google Translate
            $gCode = match ($targetLangCode) {
                'zh' => 'zh-CN',
                'sr' => 'sr',
                default => $targetLangCode,
            };

            $tr = new GoogleTranslate($gCode);
            $tr->setSource('id');

            $missingKeys = [];
            foreach ($idTranslations as $key => $value) {
                // SINKRONISASI LEBIH DALAM: Jika belum ada terjemahan ATAU terjemahan masih sama dengan bahasa Indonesia (indikasi gagal sinkron sebelumnya)
                if (! isset($targetTranslations[$key]) || ($targetTranslations[$key] === $key && ! in_array($targetLangCode, ['id', 'id_new'])) || $this->option('force')) {
                    $missingKeys[$key] = $value;
                }
            }

            if (empty($missingKeys)) {
                $this->info(" ✅ $fileName sudah sinkron.");

                continue;
            }

            $bar = $this->output->createProgressBar(count($missingKeys));
            $bar->start();

            $updatedCount = 0;
            foreach ($missingKeys as $key => $value) {
                try {
                    $translatedText = $tr->translate($key);
                    $targetTranslations[$key] = $translatedText;
                    $updatedCount++;
                } catch (\Exception $e) {
                    $this->error("\n ❌ Gagal menerjemahkan '$key': ".$e->getMessage());
                }
                $bar->advance();
                usleep(50000); // 50ms delay to avoid rate limiting
            }

            $bar->finish();
            $this->line('');

            if ($updatedCount > 0) {
                ksort($targetTranslations);
                File::put($file, json_encode($targetTranslations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
                $this->info(" ✅ $fileName berhasil diupdate!");
            }
        }

        $this->info('🏁 Selesai! Seluruh aplikasi sekarang sudah terhubung ke sistem bahasa.');
    }
}
