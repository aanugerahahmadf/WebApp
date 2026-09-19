<?php

namespace App\Traits\NativePHP\PreparesBuild;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

/**
 * Local override of Native\Mobile\Traits\PreparesBuild.
 *
 * Changes vs vendor:
 *  - prepareLaravelBundle: temp dir reads NATIVEPHP_BUILD_TEMP_DIR env (default D:\temp)
 *    instead of hardcoded C:\temp on Windows.
 */
trait PreparesBuild
{
    /**
     * Prepare Laravel bundle — override to use configurable temp dir on Windows.
     */
    /**
     * Prepare Laravel bundle — override to use configurable temp dir on Windows and robust zip unlinking.
     */
    protected function prepareLaravelBundle(bool $excludeDevDependencies = true): void
    {
        $excludeDevDependencies = true; // Force-exclude dev dependencies to prevent massive APK size (e.g. PHPUnit)
        $this->logToFile('Preparing Laravel bundle...');

        $source = realpath(base_path());
        $destinationZip = base_path('nativephp'.DIRECTORY_SEPARATOR.'android'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'assets'.DIRECTORY_SEPARATOR.'laravel_bundle.zip');

        $this->logToFile("  Source: $source");
        $this->logToFile("  Destination: $destinationZip");

        // ── KEY CHANGE: read from env instead of hardcoded C:\temp ──────────
        $tempDir = PHP_OS_FAMILY === 'Windows'
            ? (env('NATIVEPHP_BUILD_TEMP_DIR', 'D:\\Temp').'\\')
            : base_path('nativephp/android/laravel');
        // ────────────────────────────────────────────────────────────────────

        // Task 4: Log resolved temp dir path and its length to help diagnose MAX_PATH issues.
        // Windows MAX_PATH = 260 chars. Keep base path short so Composer package paths stay safe.
        $tempDirLen = strlen(rtrim($tempDir, '\\/'));
        $this->logToFile("  Temp directory: $tempDir (length: {$tempDirLen} chars)");
        if ($tempDirLen > 50) {
            $this->logToFile("  WARNING: Temp dir path is longer than 50 chars ({$tempDirLen}). Composer package extraction may hit Windows MAX_PATH (260). Consider setting NATIVEPHP_BUILD_TEMP_DIR to a shorter path like D:\\np.");
        }

        if (is_dir($tempDir)) {
            $this->logToFile('  Removing existing temp directory...');
            $this->removeDirectory($tempDir);
        }
        File::ensureDirectoryExists($tempDir);

        try {
            if (file_exists($destinationZip)) {
                $this->logToFile('  Removing existing bundle zip...');

                $unlinked = false;
                for ($i = 0; $i < 5; $i++) {
                    if (@unlink($destinationZip)) {
                        $unlinked = true;
                        break;
                    }
                    $this->logToFile('  Attempt '.($i + 1).' to remove bundle zip failed, retrying in 200ms...');
                    usleep(200000); // 200ms
                }

                if (! $unlinked) {
                    $this->logToFile('  Failed to remove bundle zip. Attempting to stop Gradle daemon to release locks...');
                    if (PHP_OS_FAMILY === 'Windows') {
                        $androidPath = base_path('nativephp'.DIRECTORY_SEPARATOR.'android');
                        $gradleWrapper = $androidPath.DIRECTORY_SEPARATOR.'gradlew.bat';
                        @exec("\"$gradleWrapper\" --stop");
                        usleep(500000); // 500ms
                    }

                    if (! @unlink($destinationZip)) {
                        $this->logToFile('WARNING: Could not remove existing bundle zip. 7-Zip will attempt to overwrite/update it.');
                    }
                }
            }

            $excludedDirs = match (PHP_OS_FAMILY) {
                'Windows' => array_merge(config('nativephp.cleanup_exclude_files'), ['.git', 'node_modules', 'nativephp', 'vendor/nativephp/mobile/resources']),
                'Linux' => array_merge(config('nativephp.cleanup_exclude_files'), ['.git', 'node_modules', 'nativephp/ios', 'nativephp/android']),
                'Darwin' => array_merge(config('nativephp.cleanup_exclude_files'), ['.git', 'node_modules', 'nativephp/ios', 'nativephp/android']),
                default => config('nativephp.cleanup_exclude_files'),
            };

            $this->logToFile('  Excluded directories: '.implode(', ', $excludedDirs));

            $srcDir = base_path('vendor'.DIRECTORY_SEPARATOR.'nativephp'.DIRECTORY_SEPARATOR.'mobile'.DIRECTORY_SEPARATOR.'bootstrap'.DIRECTORY_SEPARATOR.'android');

            $this->logToFile('  Copying Laravel source...');
            $this->components->task('Copying Laravel source', fn () => $this->platformOptimizedCopy($source, $tempDir, $excludedDirs));

            // Task 3: Guard — verify composer.json was copied before running composer install.
            // If xcopy/robocopy failed silently, this gives a clear diagnostic instead of a
            // cryptic "Composer could not find a composer.json file" error.
            $composerJsonInTemp = rtrim($tempDir, '\\/').DIRECTORY_SEPARATOR.'composer.json';
            if (! file_exists($composerJsonInTemp)) {
                $this->logToFile('ERROR: composer.json not found in temp dir after copy: '.$composerJsonInTemp);
                $this->logToFile('  Source composer.json exists: '.(file_exists($source.DIRECTORY_SEPARATOR.'composer.json') ? 'yes' : 'no'));
                \Laravel\Prompts\error('Build failed: composer.json was not copied to the temp directory.');
                \Laravel\Prompts\note('This usually means robocopy failed AND the xcopy/PHP fallback also failed.');
                \Laravel\Prompts\note("Temp dir: $tempDir");
                \Laravel\Prompts\note('Try: set NATIVEPHP_BUILD_TEMP_DIR to a shorter path (e.g. D:\\np) in your .env');
                exit(1);
            }
            $this->logToFile('  composer.json verified in temp dir ✓');

            $composerArgs = $excludeDevDependencies ? '--no-dev --no-interaction' : '--no-interaction';

            $this->logToFile('  Installing Composer dependencies'.($excludeDevDependencies ? ' (--no-dev)' : '').'...');
            $this->components->task('Installing Composer dependencies', function () use ($tempDir, $composerArgs) {
                $result = Process::path($tempDir)
                    ->timeout(0)
                    ->run("composer install {$composerArgs}");

                $this->logToFile($result->output());
                if ($result->errorOutput()) {
                    $this->logToFile($result->errorOutput());
                }

                return $result->successful();
            });

            $this->logToFile('  Optimizing autoloader...');
            $this->components->task('Optimizing autoloader', function () use ($tempDir) {
                $result = Process::path($tempDir)
                    ->timeout(0)
                    ->run('composer dump-autoload --optimize --classmap-authoritative');

                $this->logToFile($result->output());
                if ($result->errorOutput()) {
                    $this->logToFile($result->errorOutput());
                }

                return $result->successful();
            });

            $version = config('nativephp.version', now()->format('Ymd-His'));
            $this->logToFile("  Writing version file: $version");
            file_put_contents($tempDir.DIRECTORY_SEPARATOR.'.version', $version.PHP_EOL);

            if (file_exists($source.DIRECTORY_SEPARATOR.'.env')) {
                $this->logToFile('  Copying and cleaning .env file...');
                $envPath = $tempDir.DIRECTORY_SEPARATOR.'.env';
                copy($source.DIRECTORY_SEPARATOR.'.env', $envPath);
                $this->cleanEnvFile($envPath);
            }

            $artisanPhp = "{$srcDir}/artisan.php";
            if (file_exists($artisanPhp)) {
                $this->logToFile('  Copying artisan.php bootstrap...');
                File::copy($artisanPhp, "{$tempDir}/artisan.php");
            }

            $this->logToFile('  Creating bundle archive...');
            if (PHP_OS_FAMILY === 'Windows') {
                $this->logToFile('  Windows detected: sleeping 2 seconds to let filesystem and antivirus settle...');
                sleep(2);
            }
            $this->components->task('Creating bundle archive', fn () => $this->createZipBundle($tempDir, $destinationZip, $excludedDirs));

            if (! file_exists($destinationZip) || filesize($destinationZip) <= 1000) {
                $this->logToFile('ERROR: Failed to create valid zip file');
                \Laravel\Prompts\error('Failed to create valid zip file.');
                exit(1);
            }

            // Write bundle_meta.json alongside the ZIP
            $assetsDir = dirname($destinationZip);
            $bifrostAppId = null;
            if (file_exists($source.DIRECTORY_SEPARATOR.'.env')) {
                $envContent = file_get_contents($source.DIRECTORY_SEPARATOR.'.env');
                if (preg_match('/BIFROST_APP_ID=(.+)/', $envContent, $matches)) {
                    $bifrostAppId = trim($matches[1]);
                }
            }
            $bundleMeta = json_encode([
                'version' => $version,
                'bifrost_app_id' => $bifrostAppId,
                'runtime_mode' => config('nativephp.runtime.mode', 'persistent'),
            ], JSON_PRETTY_PRINT);
            file_put_contents($assetsDir.DIRECTORY_SEPARATOR.'bundle_meta.json', $bundleMeta);

            $runtimeMode = config('nativephp.runtime.mode', 'persistent');
            $this->logToFile("  Written bundle_meta.json: version=$version, bifrost=".($bifrostAppId ?? 'null').", runtime_mode=$runtimeMode");

            $sizeMB = round(filesize($destinationZip) / 1024 / 1024, 2);
            $this->logToFile("  Bundle size: {$sizeMB} MB");
            $this->components->twoColumnDetail('Bundle size', "{$sizeMB} MB");

        } finally {
            $this->logToFile('  Cleaning up temp directory...');
            $this->removeDirectory($tempDir);
        }
    }

    /**
     * Create ZIP bundle with cross-platform support — customized with -ssw switch on Windows.
     */
    protected function createZipBundle(string $source, string $destination, array $excludedDirs = []): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $sevenZip = config('nativephp.android.7zip-location');
            if (! file_exists($sevenZip)) {
                \Laravel\Prompts\error("7-Zip not found at: $sevenZip");
                exit(1);
            }

            // Added -ssw switch to allow 7-zip to read files locked/open for writing (e.g. by antivirus or other processes)
            $cmd = "\"$sevenZip\" a -tzip -ssw \"$destination\" \"$source\\*\" -xr!node_modules";
            exec($cmd, $output, $code);

            if ($code !== 0 && $code !== 1) {
                \Laravel\Prompts\error("7-Zip failed with exit code $code");
                exit(1);
            }

            if ($code === 1) {
                $this->logToFile('  7-Zip completed with warnings (non-fatal, archive successfully created). Continuing...');
            }

            return;
        }

        $zip = new \ZipArchive;
        $result = $zip->open($destination, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($result !== true) {
            \Laravel\Prompts\error("Cannot create zip file at: $destination");
            exit(1);
        }

        $this->addDirectoryToZip($zip, $source, '', $excludedDirs);

        $requiredDirs = [
            'bootstrap/cache',
            'storage/framework/cache',
            'storage/framework/sessions',
            'storage/framework/views',
        ];

        foreach ($requiredDirs as $dir) {
            if (! $zip->statName($dir)) {
                $zip->addEmptyDir($dir);
            }
        }

        $closeResult = $zip->close();
        if (! $closeResult) {
            \Laravel\Prompts\error('Failed to close ZIP file properly');
            exit(1);
        }
    }

    /**
     * Install Android icon — overridden to handle potential write/permission locks.
     */
    public function installAndroidIcon(): void
    {
        $this->logToFile('Installing Android icon...');
        $iconPath = public_path('icon.png');

        if (! File::exists($iconPath)) {
            $this->logToFile('  No icon.png found at public/icon.png, skipping');

            return;
        }

        $this->logToFile("  Source icon: $iconPath");

        $resDir = base_path('nativephp'.DIRECTORY_SEPARATOR.'android'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'main'.DIRECTORY_SEPARATOR.'res'.DIRECTORY_SEPARATOR);

        $sizes = [
            'mipmap-mdpi' => 48,
            'mipmap-hdpi' => 72,
            'mipmap-xhdpi' => 96,
            'mipmap-xxhdpi' => 144,
            'mipmap-xxxhdpi' => 192,
        ];

        $adaptiveSizes = [
            'mipmap-mdpi' => 108,
            'mipmap-hdpi' => 162,
            'mipmap-xhdpi' => 216,
            'mipmap-xxhdpi' => 324,
            'mipmap-xxxhdpi' => 432,
        ];

        $targets = [
            'ic_launcher.png',
            'ic_launcher_round.png',
            'ic_launcher_foreground.png',
        ];

        $this->logToFile('  Generating icon sizes: '.implode(', ', array_keys($sizes)));

        foreach ($sizes as $folder => $size) {
            $dstDir = $resDir.$folder;
            File::ensureDirectoryExists($dstDir);

            foreach ($targets as $filename) {
                $dstPath = $dstDir.'/'.$filename;

                $webpPath = str_replace('.png', '.webp', $dstPath);
                if (File::exists($webpPath)) {
                    @unlink($webpPath);
                }

                $targetSize = ($filename === 'ic_launcher_foreground.png') ? $adaptiveSizes[$folder] : $size;

                $this->resizePng($iconPath, $dstPath, $targetSize, $targetSize);
            }
        }

        $this->logToFile('  Android icon installed');
    }

    /**
     * Resize PNG with robust file deleting and retrying for Windows environments.
     */
    private function resizePng(string $src, string $dst, int $width, int $height): void
    {
        $srcImage = imagecreatefrompng($src);
        $srcWidth = imagesx($srcImage);
        $srcHeight = imagesy($srcImage);

        $resized = imagecreatetruecolor($width, $height);
        imagesavealpha($resized, true);
        $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
        imagefill($resized, 0, 0, $transparent);

        $isAndroidForeground = str_contains($dst, 'ic_launcher_foreground');
        // Android adaptive icons: 108dp canvas with 66dp safe zone (61%)
        // Use 0.55 to ensure icon stays within safe zone with padding for all mask shapes
        $scaleFactor = $isAndroidForeground ? 0.69 : 1.0;

        $srcRatio = $srcWidth / $srcHeight;
        $dstRatio = $width / $height;

        if ($srcRatio > $dstRatio) {
            $newWidth = (int) ($width * $scaleFactor);
            $newHeight = (int) (($width * $scaleFactor) / $srcRatio);
            $offsetX = (int) (($width - $newWidth) / 2);
            $offsetY = (int) (($height - $newHeight) / 2);
        } else {
            $newWidth = (int) (($height * $scaleFactor) * $srcRatio);
            $newHeight = (int) ($height * $scaleFactor);
            $offsetX = (int) (($width - $newWidth) / 2);
            $offsetY = (int) (($height - $newHeight) / 2);
        }

        imagecopyresampled(
            $resized, $srcImage,
            $offsetX, $offsetY, 0, 0,
            $newWidth, $newHeight,
            $srcWidth, $srcHeight
        );

        if (file_exists($dst)) {
            @unlink($dst);
        }

        $success = @imagepng($resized, $dst, 0);
        if (! $success) {
            $this->logToFile("  Warning: Failed to write icon to {$dst}. Retrying after short delay...");
            usleep(150000); // 150ms
            if (file_exists($dst)) {
                @unlink($dst);
            }
            imagepng($resized, $dst, 0);
        }

        imagedestroy($resized);
        imagedestroy($srcImage);
    }

    /**
     * Task 2: PHP-native recursive copy fallback.
     *
     * Replaces the xcopy fallback which skips root-level files (known xcopy bug with \* pattern).
     * This copies ALL files including composer.json, artisan, .env, etc. at the root level.
     * Respects $excludedDirs to skip node_modules, .git, nativephp, etc.
     */
    protected function phpNativeRecursiveCopy(string $source, string $destination, array $excludedDirs = []): void
    {
        $source = rtrim($source, '\\/');
        $destination = rtrim($destination, '\\/');

        // Normalize excluded dirs to use forward slashes for consistent matching
        $normalizedExcludes = array_map(fn ($d) => str_replace('\\', '/', trim($d, '\\/')), $excludedDirs);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $copiedFiles = 0;
        $skippedDirs = 0;

        foreach ($iterator as $item) {
            $relativePath = str_replace('\\', '/', substr($item->getRealPath(), strlen($source) + 1));

            // Check if this path should be excluded
            $shouldExclude = false;
            foreach ($normalizedExcludes as $excludedDir) {
                $normalizedExclude = rtrim($excludedDir, '/');
                if ($relativePath === $normalizedExclude
                    || str_starts_with($relativePath, $normalizedExclude.'/')
                ) {
                    $shouldExclude = true;
                    break;
                }
            }

            if ($shouldExclude) {
                $skippedDirs++;
                if ($item->isDir()) {
                    // Skip the entire directory subtree
                    $iterator->next();

                    continue;
                }

                continue;
            }

            $destPath = $destination.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $relativePath);

            if ($item->isDir()) {
                if (! is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                $destDir = dirname($destPath);
                if (! is_dir($destDir)) {
                    mkdir($destDir, 0755, true);
                }
                if (! @copy($item->getRealPath(), $destPath)) {
                    $this->logToFile("  WARNING: Could not copy file: {$item->getRealPath()} → {$destPath}");
                } else {
                    $copiedFiles++;
                }
            }
        }

        $this->logToFile("  PHP-native recursive copy completed: {$copiedFiles} files copied, {$skippedDirs} excluded paths skipped.");
    }

    /**
     * Overridden platformOptimizedCopy to fix the Windows directory separation bug
     * where forward slashes prevent Robocopy from excluding directories.
     */
    protected function platformOptimizedCopy(string $source, string $destination, array $excludedDirs = []): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // Use robocopy on Windows
            if (! empty($excludedDirs)) {
                $excludeArgs = '';
                foreach ($excludedDirs as $dir) {
                    // Robocopy on Windows requires backslashes for path matching
                    $normalizedDir = str_replace('/', '\\', $dir);
                    $excludeArgs .= " /XD \"{$source}\\{$normalizedDir}\"";
                }
                // Explicitly exclude any nested .git and node_modules directories
                $excludeArgs .= ' /XD .git node_modules';

                $cmd = "robocopy \"{$source}\" \"{$destination}\" /MIR /NFL /NDL /NJH /NJS /NP /R:0 /W:0{$excludeArgs}";
            } else {
                $cmd = "xcopy \"{$source}\\*\" \"{$destination}\\\" /E /I /Y /Q";
            }

            $this->logToFile("  Executing platformOptimizedCopy command: $cmd");
            File::ensureDirectoryExists($destination);
            exec($cmd, $output, $result);

            // Robocopy returns 0-7 as success codes
            if ($result >= 8 && strpos($cmd, 'robocopy') !== false) {
                $this->logToFile("WARNING: robocopy failed with exit code $result");
                $this->logToFile('  Retrying copy using PHP-native recursive copy fallback (xcopy skips root-level files)...');

                // Task 2: PHP-native recursive copy — reliably copies ALL files including root-level
                // (composer.json, artisan, .env, etc.) unlike xcopy with \* pattern.
                $this->phpNativeRecursiveCopy($source, $destination, $excludedDirs);
            }
        } else {
            // Use rsync on Unix-like systems
            if (! empty($excludedDirs)) {
                $excludedDirs[] = 'vendor/*/vendor';
                $excludedDirs[] = 'vendor/nativephp/mobile/vendor';
                $excludeFlags = implode(' ', array_map(fn ($d) => "--exclude='{$d}'", $excludedDirs));
                $cmd = "rsync -aL {$excludeFlags} \"{$source}/\" \"{$destination}/\"";
            } else {
                $cmd = "cp -a \"{$source}/.\" \"{$destination}/\"";
            }
            exec($cmd);
        }
    }
}
