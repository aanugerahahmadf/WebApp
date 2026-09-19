<?php

namespace App\Console\Commands\NativeEmulatorCommand;

use App\Support\AndroidSdkEnvironment\AndroidSdkEnvironment;
use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class NativeEmulatorCommand extends Command
{
    protected $signature = 'native:emulator
        {avd? : AVD name (default: NATIVEPHP_ANDROID_AVD or first available)}
        {--list : List available AVDs and exit}
        {--no-wait : Start emulator in background without waiting for boot}';

    protected $description = 'Launch the Android emulator using SDK/AVD paths on D:';

    public function handle(): int
    {
        AndroidSdkEnvironment::apply();

        $avds = AndroidSdkEnvironment::listAvds();

        if ($this->option('list')) {
            if ($avds === []) {
                $this->error('No AVDs found. Check ANDROID_AVD_HOME ('.env('ANDROID_AVD_HOME', 'D:\\Android\\avd').').');

                return self::FAILURE;
            }

            $this->info('Available AVDs:');
            foreach ($avds as $avd) {
                $this->line("  - {$avd}");
            }

            return self::SUCCESS;
        }

        $selected = $this->argument('avd') ?? AndroidSdkEnvironment::defaultAvd();

        if (! $selected) {
            $this->error('No AVD configured. Set NATIVEPHP_ANDROID_AVD in .env or pass an AVD name.');

            return self::FAILURE;
        }

        if (! in_array($selected, $avds, true)) {
            $this->error("AVD '{$selected}' not found.");
            $this->line('Run: php artisan native:emulator --list');

            return self::FAILURE;
        }

        $emulator = env('ANDROID_EMULATOR') ?? config('nativephp.android.emulator_path');

        if (! $emulator || ! is_file($emulator)) {
            $this->error('Android emulator binary not found. Set ANDROID_EMULATOR in .env.');

            return self::FAILURE;
        }

        $this->info("Launching emulator: {$selected}");

        if (PHP_OS_FAMILY === 'Windows') {
            $launchCommand = sprintf('start /B "" "%s" -avd "%s"', $emulator, $selected);
        } else {
            $launchCommand = sprintf('nohup %s -avd %s > /tmp/emulator.log 2>&1 &', escapeshellarg($emulator), escapeshellarg($selected));
        }

        Process::fromShellCommandline($launchCommand)->start();

        if ($this->option('no-wait')) {
            $this->comment('Emulator starting in background.');

            return self::SUCCESS;
        }

        $this->components->task('Waiting for emulator to boot', function () {
            for ($i = 0; $i < 120; $i++) {
                $boot = trim((string) shell_exec('adb shell getprop sys.boot_completed'));
                $anim = trim((string) shell_exec('adb shell getprop init.svc.bootanim'));

                if ($boot === '1' && $anim === 'stopped') {
                    return true;
                }

                usleep(500000);
            }

            return false;
        });

        $devices = trim((string) shell_exec('adb devices'));

        $this->newLine();
        $this->line($devices);
        $this->info('Emulator ready. Run: php artisan native:run android');

        return self::SUCCESS;
    }
}
