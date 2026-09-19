<?php

namespace App\Traits\NativePHP\RunsIos;

use Illuminate\Support\Facades\Process;

use function Laravel\Prompts\error;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\warning;

/**
 * Local override of Native\Mobile\Traits\RunsIos.
 *
 * Changes vs vendor:
 *  - runOnRealDevice: timeout(300) → timeout(0) for device install
 *  - runOnRealDevice: timeout(30)  → timeout(0) for app launch
 */
trait RunsIos
{
    private function runOnRealDevice(string $basePath, string $target, bool $verbose = false): void
    {
        $installFailed = false;
        $isRelease = $this->option('build') === 'release';
        $configuration = $isRelease ? 'Release' : 'Debug';

        $this->components->task('Deploying app to device', function () use ($basePath, $target, $verbose, &$installFailed, $configuration) {
            $installResult = Process::path($basePath)
                ->timeout(0)   // ← no timeout (was 300s)
                ->tty($verbose && ! $this->option('no-tty'))
                ->run([
                    'xcrun', 'devicectl', 'device', 'install', 'app',
                    '--device', $target,
                    "build/Build/Products/{$configuration}-iphoneos/NativePHP.app",
                ], function ($type, $output) use ($verbose) {
                    file_put_contents($this->iosLogPath, $output, FILE_APPEND);
                    if ($verbose) {
                        $this->output->write($output);
                    }
                });

            if (! $installResult->successful()) {
                $installFailed = true;

                return false;
            }

            return true;
        });

        if ($installFailed) {
            error('App installation failed!');
            note('Check nativephp/ios-build.log for details.');

            return;
        }

        $appId = config('nativephp.app_id');
        $launchFailed = false;

        $this->components->task('Launching app', function () use ($basePath, $target, $appId, $verbose, &$launchFailed) {
            $launchResult = Process::path($basePath)
                ->timeout(0)   // ← no timeout (was 30s)
                ->run([
                    'xcrun', 'devicectl', 'device', 'process', 'launch',
                    '--device', $target,
                    $appId,
                ], function ($type, $output) use ($verbose) {
                    file_put_contents($this->iosLogPath, $output, FILE_APPEND);
                    if ($verbose) {
                        $this->output->write($output);
                    }
                });

            if (! $launchResult->successful()) {
                $launchFailed = true;

                return false;
            }

            return true;
        });

        if ($launchFailed) {
            warning('App installed but launch failed - tap the app icon on your device.');
        } else {
            outro('App launched!');
        }
    }
}
