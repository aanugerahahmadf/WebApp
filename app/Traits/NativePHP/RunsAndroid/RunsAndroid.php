<?php

namespace App\Traits\NativePHP\RunsAndroid;

use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\error;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\warning;

/**
 * Local override of Native\Mobile\Traits\RunsAndroid.
 *
 * Changes vs vendor:
 *  - runAndroid: pre-launches emulator with correct ANDROID_AVD_HOME env (D:\Android\avd)
 *    and waits for ADB to come online before handing off to vendor code.
 *    Fixes "Unknown AVD name" and "No connected Android devices" on Windows with D: drive.
 *  - runTheAndroidBuild: no TTY on Windows (TTY not supported), no timeout for large APKs.
 */
trait RunsAndroid
{
    /**
     * Override runAndroid to:
     * 1. Ensure ANDROID_AVD_HOME is visible to child processes on Windows
     * 2. Pre-launch emulator with correct env if no device is connected
     * 3. Wait for ADB to come online before handing off to vendor code
     */
    public function runAndroid(): void
    {
        if ($this->buildType === 'debug') {
            $this->ensureAndroidEnvForChildProcesses();
            $this->ensureAndroidDeviceReady();
        }

        // Delegate to vendor runAndroid (which handles all the real build logic)
        $this->vendorRunAndroid();
    }

    /**
     * Ensure Android SDK environment variables are set at the Windows process level
     * so that child processes spawned by SymfonyProcess inherit them correctly.
     *
     * putenv() only affects PHP's own environment — child processes on Windows
     * inherit from the Win32 process environment block, which requires SetEnvironmentVariable.
     */
    private function ensureAndroidEnvForChildProcesses(): void
    {
        $vars = [
            'ANDROID_AVD_HOME' => env('ANDROID_AVD_HOME', 'D:\\Android\\avd'),
            'ANDROID_SDK_ROOT' => env('ANDROID_SDK_ROOT', 'D:\\Android\\Sdk'),
            'ANDROID_HOME' => env('ANDROID_HOME', 'D:\\Android\\Sdk'),
            'JAVA_HOME' => env('JAVA_HOME', 'D:\\Android\\Sdk\\jdk-26.0.1'),
        ];

        foreach ($vars as $key => $value) {
            if ($value) {
                putenv("{$key}={$value}");
                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
            }
        }
    }

    /**
     * If no ADB device is online, launch the configured emulator ourselves
     * (with the correct ANDROID_AVD_HOME env) and wait for it to come online.
     *
     * This replaces the vendor flow which uses SymfonyProcess::start() without
     * passing env vars, causing "Unknown AVD name" on Windows when AVDs live on D:.
     */
    private function ensureAndroidDeviceReady(): void
    {
        $adbCommand = PHP_OS_FAMILY === 'Windows' ? 'adb.exe' : 'adb';

        // Restart ADB server to clear stale connections
        shell_exec("$adbCommand kill-server 2>&1");
        sleep(1);
        shell_exec("$adbCommand start-server 2>&1");
        sleep(2);

        // If a device is already online, nothing to do
        if ($this->hasOnlineAdbDevice($adbCommand)) {
            return;
        }

        // Check for offline devices (emulator booting but ADB not yet authenticated)
        $rawOutput = shell_exec("$adbCommand devices") ?: '';
        if (str_contains($rawOutput, 'offline') || str_contains($rawOutput, 'emulator-')) {
            $this->line('  Emulator detected but offline. Waiting for ADB authentication...');
            $this->waitForAdbDeviceOnline($adbCommand, 60);

            return;
        }

        // No device at all — launch the configured AVD ourselves with correct env
        $avdName = env('NATIVEPHP_ANDROID_AVD', config('nativephp.android.default_avd'));
        $emulatorBin = env('ANDROID_EMULATOR', config('nativephp.android.emulator_path',
            'D:\\Android\\Sdk\\emulator\\emulator.exe'));

        if (! $avdName || ! file_exists($emulatorBin)) {
            // No AVD configured or emulator not found — let vendor code handle it
            return;
        }

        $avdHome = env('ANDROID_AVD_HOME', 'D:\\Android\\avd');
        $sdkRoot = env('ANDROID_SDK_ROOT', 'D:\\Android\\Sdk');

        $this->line("  No device found. Launching emulator: {$avdName}");

        // On Windows, use Start-Process PowerShell to launch emulator with window.
        // Using cmd /C start has quoting issues. Using -no-window causes ADB to stay
        // "unauthorized" because the "Allow USB debugging?" dialog never appears.
        if (PHP_OS_FAMILY === 'Windows') {
            $psCmd = 'powershell -Command "$env:ANDROID_AVD_HOME=\''.$avdHome.'\'; $env:ANDROID_SDK_ROOT=\''.$sdkRoot.'\'; $env:ANDROID_HOME=\''.$sdkRoot.'\'; Start-Process -FilePath \''.$emulatorBin.'\' -ArgumentList \'-avd\',\''.$avdName.'\' -WindowStyle Normal"';
            pclose(popen($psCmd, 'r'));
        } else {
            $cmd = 'ANDROID_AVD_HOME="'.$avdHome.'" ANDROID_SDK_ROOT="'.$sdkRoot.'" nohup "'.$emulatorBin.'" -avd "'.$avdName.'" > /tmp/emulator.log 2>&1 &';
            pclose(popen($cmd, 'r'));
        }

        $this->line('  Waiting for emulator to boot (this may take 1-2 minutes)...');
        $this->waitForAdbDeviceOnline($adbCommand, 180);
    }

    /**
     * Wait up to $maxSeconds for at least one ADB device to come online.
     */
    private function waitForAdbDeviceOnline(string $adbCommand, int $maxSeconds = 90): void
    {
        $elapsed = 0;
        $interval = 3;

        while ($elapsed < $maxSeconds) {
            sleep($interval);
            $elapsed += $interval;

            // Try reconnecting via TCP (helps with emulator-5554 offline)
            shell_exec("$adbCommand connect 127.0.0.1:5555 2>&1");

            if ($this->hasOnlineAdbDevice($adbCommand)) {
                $this->line("  ✓ ADB device online after {$elapsed}s");

                return;
            }

            if ($elapsed % 15 === 0) {
                $this->line("  Still waiting for ADB... ({$elapsed}s / {$maxSeconds}s)");
            }
        }

        $this->warn("  ADB device did not come online after {$maxSeconds}s.");
    }

    /**
     * Returns true if at least one ADB device/emulator is online (status = "device").
     */
    private function hasOnlineAdbDevice(string $adbCommand): bool
    {
        $output = shell_exec("$adbCommand devices") ?: '';

        return (bool) preg_match('/\tdevice\b/', $output);
    }

    /**
     * Override: run Gradle build + adb install with no timeout.
     * TTY is removed from Windows branch — not supported on Windows platform.
     */
    private function runTheAndroidBuild(?string $targetDeviceId): void
    {
        $androidPath = base_path('nativephp'.DIRECTORY_SEPARATOR.'android');
        $gradleWrapper = PHP_OS_FAMILY === 'Windows'
            ? $androidPath.DIRECTORY_SEPARATOR.'gradlew.bat'
            : $androidPath.DIRECTORY_SEPARATOR.'gradlew';

        if (PHP_OS_FAMILY !== 'Windows') {
            $gradlePath = $androidPath.DIRECTORY_SEPARATOR.'gradlew';
            if (! is_executable($gradlePath)) {
                chmod($gradlePath, 0755);
            }
        }

        $gradleTask = match ($this->buildType) {
            'debug' => 'assembleDebug',
            'release' => 'assembleRelease',
            'bundle' => 'bundleRelease',
            default => throw new \Exception("Unknown build type: $this->buildType"),
        };

        $verbose = $this->getOutput()->isVerbose();

        $this->components->twoColumnDetail('Build type', $this->buildType);
        $this->components->twoColumnDetail('App version', config('nativephp.version', 'Not set'));
        $this->newLine();

        $this->logToFile('--- Starting Gradle Build ---');
        $this->logToFile("Gradle wrapper: $gradleWrapper");
        $this->logToFile("Gradle task: $gradleTask");
        $this->logToFile('Verbose mode: '.($verbose ? 'enabled' : 'disabled'));

        $buildSuccessful = false;

        if (PHP_OS_FAMILY === 'Windows') {
            // TTY is NOT supported on Windows — never call ->tty() here.
            // Output is captured via the closure callback and echoed in real-time.
            // IMPORTANT: must use ->path($androidPath) so gradlew.bat resolves APP_HOME correctly.
            $result = Process::path($androidPath)->timeout(0)->run("\"$gradleWrapper\" $gradleTask", function ($type, $output) {
                echo $output;
                file_put_contents($this->androidLogPath, $output, FILE_APPEND);
            });

            $this->logToFile('Windows build exit code: '.$result->exitCode());
            $buildSuccessful = $result->successful();
        } else {
            $process = Process::path($androidPath)->timeout(0);

            // TTY only makes sense on Unix — guard with --no-tty option
            if (! $this->option('no-tty')) {
                $process->tty();
            }

            $result = $process->run("$gradleWrapper $gradleTask", function ($type, $output) {
                file_put_contents($this->androidLogPath, $output, FILE_APPEND);
            });

            if (! $result->successful()) {
                $this->logToFile('ERROR: Gradle build failed with exit code: '.$result->exitCode());
                error('Gradle build failed');
                note("Check the build log for details: {$this->androidLogPath}");

                return;
            }

            $buildSuccessful = $result->successful();
        }

        if (! $buildSuccessful) {
            $this->logToFile('ERROR: Build failed');
            error('Build failed.');
            note("Check the build log for details: {$this->androidLogPath}");

            return;
        }

        $this->logToFile('Gradle build completed successfully');

        if ($this->buildType === 'debug') {
            $appId = config('nativephp.app_id');
            $mainActivity = 'com.nativephp.mobile.ui.MainActivity';
            $adbCommand = PHP_OS_FAMILY === 'Windows' ? 'adb.exe' : 'adb';

            $apkPath = base_path('nativephp'.DIRECTORY_SEPARATOR.'android'.DIRECTORY_SEPARATOR.'app'.DIRECTORY_SEPARATOR.'build'.DIRECTORY_SEPARATOR.'outputs'.DIRECTORY_SEPARATOR.'apk'.DIRECTORY_SEPARATOR.'debug'.DIRECTORY_SEPARATOR.'app-debug.apk');
            $installCmd = "$adbCommand -s $targetDeviceId install -r \"$apkPath\"";
            $this->logToFile("Installing APK: $installCmd");

            // timeout(0) — no limit for large APK installs
            $installResult = Process::timeout(0)->run($installCmd);

            if (! $installResult->successful()) {
                // Coba uninstall dulu jika gagal karena signature mismatch (INSTALL_FAILED_UPDATE_INCOMPATIBLE)
                $errOutput = $installResult->errorOutput() ?: $installResult->output();
                if (str_contains($errOutput, 'INSTALL_FAILED_UPDATE_INCOMPATIBLE')) {
                    $this->logToFile('Signature mismatch detected. Uninstalling old APK and retrying...');
                    $uninstallResult = Process::timeout(30)->run("$adbCommand -s $targetDeviceId uninstall $appId");
                    $this->logToFile('Uninstall result: '.$uninstallResult->output());

                    $installResult = Process::timeout(0)->run($installCmd);
                }
            }

            if (! $installResult->successful()) {
                $this->logToFile('ERROR: APK installation failed');
                $this->logToFile($installResult->output());
                $this->logToFile($installResult->errorOutput());
                error('APK installation failed');
                note($installResult->errorOutput() ?: $installResult->output());
                note('Try freeing up space on the device or uninstalling old apps.');

                return;
            }

            $this->logToFile('APK installed on device');

            $launchCmd = "$adbCommand -s $targetDeviceId shell am start -n $appId/$mainActivity";
            $this->logToFile("Launching app: $launchCmd");

            $launchResult = Process::timeout(0)->run($launchCmd);

            if (! $launchResult->successful()) {
                $this->logToFile('ERROR: App launch failed');
                $this->logToFile($launchResult->errorOutput());
                error('App launch failed');
                note($launchResult->errorOutput() ?: $launchResult->output());

                return;
            }

            $this->logToFile('App launched on device');
            outro('App launched!');

            $this->runAndroidPostBuildHooks();

        } else {
            $outputPath = match ($this->buildType) {
                'release' => $this->findReleaseApk(),
                'bundle' => base_path('nativephp/android/app/build/outputs/bundle/release/app-release.aab'),
                default => null,
            };

            if ($outputPath) {
                $outputPath = str_replace(['\\', "\r", "\n"], ['/', '', ''], $outputPath);
            }

            if ($outputPath && file_exists($outputPath)) {
                $fileSize = round(filesize($outputPath) / 1024 / 1024, 2);
                $this->logToFile("Build output: $outputPath");
                $this->logToFile("Output size: {$fileSize} MB");
                $this->components->twoColumnDetail('Output', $outputPath);

                if (PHP_OS_FAMILY === 'Windows') {
                    $windowsPath = str_replace('/', '\\', $outputPath);
                    $windowsPath = escapeshellarg($windowsPath);
                    exec("explorer.exe /select,$windowsPath");
                } elseif (PHP_OS_FAMILY === 'Darwin') {
                    exec("open -R \"$outputPath\"");
                } elseif (PHP_OS_FAMILY === 'Linux') {
                    if (shell_exec('which xdg-open')) {
                        exec('xdg-open "'.dirname($outputPath).'"');
                    }
                }
            } else {
                warning("Could not locate output file for build type: $this->buildType");
            }

            outro('Build complete!');

            $this->runAndroidPostBuildHooks();
        }
    }
}
