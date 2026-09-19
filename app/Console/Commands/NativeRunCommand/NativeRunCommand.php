<?php

namespace App\Console\Commands\NativeRunCommand;

use App\Support\AndroidSdkEnvironment\AndroidSdkEnvironment;
use App\Traits\NativePHP\PackagesIos\PackagesIos as LocalPackagesIos;
use App\Traits\NativePHP\PreparesBuild\PreparesBuild as LocalPreparesBuild;
use App\Traits\NativePHP\RunsAndroid\RunsAndroid as LocalRunsAndroid;
use App\Traits\NativePHP\RunsIos\RunsIos as LocalRunsIos;
use GuzzleHttp\Client;
use Illuminate\Console\Command;
use Native\Mobile\Plugins\PluginRegistry;
use Native\Mobile\Traits\DisplaysMarketingBanners;
use Native\Mobile\Traits\ManagesViteDevServer;
use Native\Mobile\Traits\ManagesWatchman;
use Native\Mobile\Traits\PackagesIos as VendorPackagesIos;
use Native\Mobile\Traits\PlatformFileOperations;
use Native\Mobile\Traits\RunsAndroid as VendorRunsAndroid;
use Native\Mobile\Traits\RunsIos as VendorRunsIos;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\warning;

/**
 * Standalone override of vendor native:run (does NOT extend RunCommand).
 *
 * Avoids PHP trait conflict — private methods in vendor traits cannot be
 * overridden via inheritance, so we use explicit insteadof resolution.
 *
 * Improvements:
 *  1. Auto-prefer physical Android device over emulator.
 *  2. PreparesBuild  — NATIVEPHP_BUILD_TEMP_DIR env (default D:\temp).
 *  3. RunsAndroid    — adb install/launch with timeout(0).
 *  4. RunsIos        — devicectl install/launch with timeout(0).
 *  5. PackagesIos    — xcodebuild & altool with timeout(0).
 */
class NativeRunCommand extends Command
{
    use DisplaysMarketingBanners,
        ManagesViteDevServer,
        ManagesWatchman,
        PlatformFileOperations;
    use LocalPackagesIos, VendorPackagesIos {
        // LocalPackagesIos methods win (no timeout)
        LocalPackagesIos::prepareBuildEnvironment  insteadof VendorPackagesIos;
        LocalPackagesIos::exportArchiveWithXcode   insteadof VendorPackagesIos;
        LocalPackagesIos::uploadToAppStore         insteadof VendorPackagesIos;
        VendorPackagesIos::prepareBuildEnvironment as vendorPrepareBuildEnvironment;
        VendorPackagesIos::exportArchiveWithXcode as vendorExportArchiveWithXcode;
        VendorPackagesIos::uploadToAppStore as vendorUploadToAppStore;
    }

    // ── PreparesBuild ─────────────────────────────────────────────────────
    // VendorRunsAndroid already uses vendor PreparesBuild via its own trait chain.
    // We override prepareLaravelBundle, createZipBundle, installAndroidIcon, and resizePng via LocalPreparesBuild.
    use LocalPreparesBuild {
        LocalPreparesBuild::prepareLaravelBundle insteadof VendorRunsAndroid;
        LocalPreparesBuild::createZipBundle insteadof VendorRunsAndroid;
        LocalPreparesBuild::installAndroidIcon insteadof VendorRunsAndroid;
        LocalPreparesBuild::resizePng insteadof VendorRunsAndroid;
        LocalPreparesBuild::platformOptimizedCopy insteadof PlatformFileOperations;
    }

    // ── Android traits ────────────────────────────────────────────────────
    use LocalRunsAndroid, VendorRunsAndroid {
        // LocalRunsAndroid::runAndroid wins (adds ADB reconnect before device selection)
        LocalRunsAndroid::runAndroid insteadof VendorRunsAndroid;
        VendorRunsAndroid::runAndroid as vendorRunAndroid;
        // LocalRunsAndroid::runTheAndroidBuild wins (no TTY on Windows, no timeout)
        LocalRunsAndroid::runTheAndroidBuild insteadof VendorRunsAndroid;
        VendorRunsAndroid::runTheAndroidBuild as vendorRunTheAndroidBuild;
    }

    // ── iOS traits ────────────────────────────────────────────────────────
    use LocalRunsIos, VendorRunsIos {
        // LocalRunsIos::runOnRealDevice wins (no timeout)
        LocalRunsIos::runOnRealDevice    insteadof VendorRunsIos;
        VendorRunsIos::runOnRealDevice as vendorRunOnRealDevice;
    }

    protected $signature = 'native:run
        {os? : Platform to run (android/a or ios/i)}
        {udid? : ADB device serial — run `adb devices` to list (auto-detects physical device if omitted)}
        {--build=debug : debug|release|bundle}
        {--W|watch : Enable hot reloading during development}
        {--start-url= : Set the initial URL/path to load on app start (e.g., /dashboard)}
        {--no-tty : Disable TTY mode for non-interactive environments}';

    protected $description = 'Build, package, and run the NativePHP app (no timeouts, auto-prefers physical device)';

    protected string $buildType;

    public function handle(): int
    {
        AndroidSdkEnvironment::apply();

        $this->ensureValidAppId();

        if ($this->option('watch') && ! $this->checkWatchmanDependencies()) {
            return self::FAILURE;
        }

        if ($startUrl = $this->option('start-url')) {
            $this->updateStartUrl($startUrl);
        }

        $nativephpDir = base_path('nativephp');
        if (! is_dir($nativephpDir)) {
            mkdir($nativephpDir, 0755, true);
        }

        $os = $this->argument('os');
        if ($os && in_array(strtolower($os), ['a', 'i', 'android', 'ios'])) {
            $os = match (strtolower($os)) {
                'android', 'a' => 'android',
                'ios', 'i' => 'ios',
            };
        }

        if ($this->isRunningInWSL()) {
            $this->warn('Running in WSL. Android builds require adb and the Android SDK to be installed inside WSL.');
            $this->line('  Install: sudo apt install adb && export ANDROID_SDK_ROOT=~/Android/Sdk');
        }

        if (! $os) {
            if (PHP_OS_FAMILY === 'Darwin') {
                $hasAndroid = is_dir(base_path('nativephp/android'));
                $hasIos = is_dir(base_path('nativephp/ios'));

                if ($hasAndroid && ! $hasIos) {
                    $os = 'android';
                } elseif ($hasIos && ! $hasAndroid) {
                    $os = 'ios';
                } else {
                    $os = select('Which platform would you like to run?', [
                        'android' => 'Android',
                        'ios' => 'iOS',
                    ]);
                }
            } else {
                $os = 'android';
            }
        }

        // Auto-prefer physical Android device when multiple devices connected
        if ($os === 'android' && ! $this->argument('udid')) {
            $resolved = $this->resolvePhysicalDevice();
            if ($resolved) {
                $this->input->setArgument('udid', $resolved);
            }
        }

        $buildTypes = ['debug' => 'Debug', 'release' => 'Release'];
        if ($os === 'android') {
            $buildTypes['bundle'] = 'App Bundle (AAB)';
        }

        $this->buildType = $this->option('build') ?? select(
            label: 'Choose a build type',
            options: $buildTypes,
            default: 'debug'
        );

        $osName = match ($os) {
            'android' => 'Android',
            'ios' => 'iOS',
            default => throw new \Exception('Invalid OS type.'),
        };

        intro('Running NativePHP for '.$osName);

        if (! $this->checkForPhpBinaryUpdates()) {
            return self::FAILURE;
        }

        $this->checkForUnregisteredPlugins();

        match ($os) {
            'android' => $this->runAndroid(),
            'ios' => $this->runIos(),
        };

        $this->showBifrostBanner();

        return self::SUCCESS;
    }

    // ──────────────────────────────────────────────────────────────────────
    // AUTO-PREFER PHYSICAL ANDROID DEVICE
    // ──────────────────────────────────────────────────────────────────────

    private function resolvePhysicalDevice(): ?string
    {
        $output = shell_exec('adb devices') ?: '';
        $lines = array_values(array_filter(
            explode("\n", $output),
            fn ($line) => str_contains($line, "\tdevice")
        ));
        $devices = array_map(fn ($line) => explode("\t", trim($line))[0], $lines);

        if (count($devices) <= 1) {
            return null;
        }

        $physical = array_values(array_filter(
            $devices,
            fn ($d) => ! str_starts_with($d, 'emulator')
        ));

        if (! empty($physical)) {
            $this->line("⚡ Multiple devices detected. Auto-selecting physical device: <info>{$physical[0]}</info>");
            $this->line("   (Pass the UDID argument to target a specific device)\n");

            return $physical[0];
        }

        return null;
    }

    // ──────────────────────────────────────────────────────────────────────
    // COPIED FROM vendor RunCommand — unchanged
    // ──────────────────────────────────────────────────────────────────────

    protected function checkForPhpBinaryUpdates(): bool
    {
        try {
            $jsonPath = base_path('nativephp.json');

            if (! file_exists($jsonPath)) {
                return true;
            }

            $nativephp = json_decode(file_get_contents($jsonPath), true) ?? [];
            $installedVersion = $nativephp['php']['version'] ?? null;

            if (! $installedVersion) {
                return true;
            }

            $parts = explode('.', $installedVersion);
            $installedMinor = $parts[0].'.'.$parts[1];
            $runningMinor = PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;

            if ($installedMinor !== $runningMinor) {
                warning("PHP version mismatch:\n  • Mobile PHP version: {$installedMinor}\n  • CLI PHP version: {$runningMinor}\n\nYour app will not run.");

                if (confirm('Run native:install again to fix this?', default: true)) {
                    $this->call('native:install', ['--force' => true]);

                    return true;
                }

                return false;
            }

            $branch = env('NATIVEPHP_BIN_BRANCH', 'main');
            $client = new Client;
            $response = $client->get("https://bin.nativephp.com/{$branch}/versions.json", [
                'connect_timeout' => 3,
                'timeout' => 3,
            ]);

            $versions = json_decode($response->getBody()->getContents(), true);
            $latestVersion = $versions['versions'][$installedMinor]['php_version'] ?? null;

            if ($latestVersion && version_compare($latestVersion, $installedVersion, '>')) {
                note("PHP {$latestVersion} is available (installed: {$installedVersion}). Run <comment>php artisan native:install --force</comment> to update.");
            }
        } catch (\Throwable) {
            // Fail silently
        }

        return true;
    }

    protected function checkForUnregisteredPlugins(): void
    {
        $registry = app(PluginRegistry::class);
        $unregistered = $registry->unregistered();

        if ($unregistered->isEmpty()) {
            return;
        }

        warning('The following plugins are installed but not registered:');
        $unregistered->each(function ($plugin) {
            $this->components->twoColumnDetail($plugin->name, '<fg=yellow>not registered</>');
        });
        note('Register them in your NativeServiceProvider or run: php artisan native:plugin:register');
        $this->newLine();
    }

    protected function ensureValidAppId(): void
    {
        $appId = config('nativephp.app_id');

        if (str($appId)->isEmpty()) {
            error('NATIVEPHP_APP_ID is not set.');
            note('Please add a NATIVEPHP_APP_ID to your .env file (e.g. com.example.myapp).');
            exit(1);
        }

        if (str($appId)->startsWith('com.nativephp.')) {
            warning('Please change your NATIVEPHP_APP_ID from the default value.');
        }
    }

    protected function updateStartUrl(string $startUrl): void
    {
        $envFilePath = base_path('.env');

        if (! file_exists($envFilePath)) {
            error('.env file not found');

            return;
        }

        $envContent = file_get_contents($envFilePath);
        $key = 'NATIVEPHP_START_URL';
        $newLine = "{$key}={$startUrl}";

        if (preg_match("/^{$key}=.*$/m", $envContent)) {
            $envContent = preg_replace("/^{$key}=.*$/m", $newLine, $envContent);
        } else {
            $envContent = rtrim($envContent).PHP_EOL.$newLine.PHP_EOL;
        }

        file_put_contents($envFilePath, $envContent);
        $this->components->twoColumnDetail('Start URL', $startUrl);
    }
}
