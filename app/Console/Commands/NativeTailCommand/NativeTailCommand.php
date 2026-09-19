<?php

namespace App\Console\Commands\NativeTailCommand;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

use function Laravel\Prompts\select;

/**
 * Override vendor native:tail.
 *
 * Improvements vs vendor:
 *  - Support Android + iOS (simulator & physical device)
 *  - Auto-detect platform and device
 *  - Android: prefer physical device over emulator when multiple connected
 *  - iOS simulator: tail via xcrun simctl
 *  - iOS physical: tail via idevicesyslog (libimobiledevice)
 *  - Support --device=SERIAL and --platform=android|ios flags
 */
class NativeTailCommand extends Command
{
    protected $signature = 'native:tail
        {--platform= : android or ios (auto-detected if omitted)}
        {--device=   : Device serial/UDID — run `adb devices` or `xcrun xctrace list devices`}';

    protected $description = 'Tail Laravel logs from Android or iOS app (simulator & physical device)';

    public function handle(): void
    {
        $appId = config('nativephp.app_id');

        if (empty($appId)) {
            $this->error('🚫 NATIVEPHP_APP_ID is not set.');
            $this->line('Please add NATIVEPHP_APP_ID to your .env file (e.g. com.example.myapp).');

            return;
        }

        $platform = $this->option('platform') ?: $this->detectPlatform();

        match ($platform) {
            'ios' => $this->tailIos($appId),
            'android' => $this->tailAndroid($appId),
            default => $this->error("Unknown platform: {$platform}. Use android or ios."),
        };
    }

    // ──────────────────────────────────────────────────────────────────────
    // PLATFORM DETECTION
    // ──────────────────────────────────────────────────────────────────────

    private function detectPlatform(): string
    {
        $hasAndroid = $this->hasConnectedAndroidDevices();
        $hasIos = PHP_OS_FAMILY === 'Darwin'; // iOS tools only on macOS

        if ($hasAndroid && ! $hasIos) {
            return 'android';
        }

        if ($hasIos && ! $hasAndroid) {
            return 'ios';
        }

        if ($hasAndroid && $hasIos) {
            return select('Select platform to tail logs from', [
                'android' => 'Android',
                'ios' => 'iOS',
            ]);
        }

        // Default to android
        return 'android';
    }

    private function hasConnectedAndroidDevices(): bool
    {
        $output = shell_exec('adb devices') ?: '';
        $devices = array_filter(
            explode("\n", $output),
            fn ($l) => str_contains($l, "\tdevice")
        );

        return ! empty($devices);
    }

    // ──────────────────────────────────────────────────────────────────────
    // ANDROID
    // ──────────────────────────────────────────────────────────────────────

    private function tailAndroid(string $appId): void
    {
        $device = $this->option('device') ?: $this->resolveAndroidDevice();

        if (! $device) {
            return;
        }

        $this->info("🤖 Tailing Android logs — app: {$appId}");
        $this->info("📱 Device: {$device}");
        $this->line("Press Ctrl+C to stop...\n");

        $process = new Process([
            'adb', '-s', $device,
            'shell', 'run-as', $appId,
            'tail', '-f',
            'app_storage/persisted_data/storage/logs/laravel.log',
        ]);
        $process->setTimeout(null);

        $this->streamProcess($process);
    }

    private function resolveAndroidDevice(): ?string
    {
        $output = shell_exec('adb devices') ?: '';
        $lines = array_values(array_filter(
            explode("\n", $output),
            fn ($l) => str_contains($l, "\tdevice")
        ));
        $devices = array_map(fn ($l) => explode("\t", trim($l))[0], $lines);

        if (empty($devices)) {
            $this->error('❌ No connected Android devices found.');
            $this->line('Enable USB Debugging and connect your device.');

            return null;
        }

        if (count($devices) === 1) {
            return $devices[0];
        }

        // Multiple — prefer physical over emulator
        $physical = array_values(array_filter($devices, fn ($d) => ! str_starts_with($d, 'emulator')));
        $chosen = ! empty($physical) ? $physical[0] : $devices[0];

        $this->line("⚡ Multiple devices found. Using: <info>{$chosen}</info> (--device=SERIAL to override)\n");

        return $chosen;
    }

    // ──────────────────────────────────────────────────────────────────────
    // iOS
    // ──────────────────────────────────────────────────────────────────────

    private function tailIos(string $appId): void
    {
        $device = $this->option('device') ?: $this->resolveIosDevice();

        if (! $device) {
            return;
        }

        // Determine if it's a simulator (UUID format) or physical device
        $isSimulator = $this->isIosSimulator($device);

        if ($isSimulator) {
            $this->tailIosSimulator($appId, $device);
        } else {
            $this->tailIosPhysical($appId, $device);
        }
    }

    private function resolveIosDevice(): ?string
    {
        // Check booted simulators first
        $bootedOutput = shell_exec('xcrun simctl list devices booted --json 2>/dev/null') ?: '';
        $bootedData = json_decode($bootedOutput, true);
        $booted = [];

        if (! empty($bootedData['devices'])) {
            foreach ($bootedData['devices'] as $runtime => $devices) {
                foreach ($devices as $device) {
                    if ($device['state'] === 'Booted') {
                        $booted[$device['udid']] = $device['name'].' (Simulator, Booted)';
                    }
                }
            }
        }

        // Check connected physical devices
        $physicalOutput = shell_exec('xcrun devicectl list devices 2>/dev/null') ?: '';
        $physical = [];
        foreach (explode("\n", $physicalOutput) as $line) {
            if (preg_match('/([0-9A-F]{8}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{4}-[0-9A-F]{12})\s+(.+)/i', $line, $m)) {
                $physical[$m[1]] = trim($m[2]).' (Physical)';
            }
        }

        $all = array_merge($booted, $physical);

        if (empty($all)) {
            $this->error('❌ No iOS devices or simulators found.');
            $this->line('Boot a simulator or connect a physical device.');

            return null;
        }

        if (count($all) === 1) {
            return array_key_first($all);
        }

        return select('Select iOS device/simulator', $all);
    }

    private function isIosSimulator(string $udid): bool
    {
        $output = shell_exec('xcrun simctl list devices --json 2>/dev/null') ?: '';
        $data = json_decode($output, true);

        if (empty($data['devices'])) {
            return false;
        }

        foreach ($data['devices'] as $devices) {
            foreach ($devices as $device) {
                if ($device['udid'] === $udid) {
                    return true;
                }
            }
        }

        return false;
    }

    private function tailIosSimulator(string $appId, string $udid): void
    {
        $this->info("🍎 Tailing iOS Simulator logs — app: {$appId}");
        $this->info("📱 Simulator UDID: {$udid}");
        $this->line("Press Ctrl+C to stop...\n");

        // Get app container path in simulator
        $containerPath = trim(shell_exec("xcrun simctl get_app_container {$udid} {$appId} data 2>/dev/null") ?: '');

        if (empty($containerPath)) {
            $this->error("❌ Could not find app container for {$appId} on simulator {$udid}.");
            $this->line('Make sure the app is installed and has been launched at least once.');

            return;
        }

        $logPath = $containerPath.'/Library/Caches/storage/logs/laravel.log';

        if (! file_exists($logPath)) {
            // Try alternative path
            $logPath = $containerPath.'/Documents/storage/logs/laravel.log';
        }

        if (! file_exists($logPath)) {
            $this->warn('⚠️  Log file not found at expected path.');
            $this->line("Container: {$containerPath}");
            $this->line('The app may not have generated logs yet. Launch the app first.');

            return;
        }

        $process = new Process(['tail', '-f', $logPath]);
        $process->setTimeout(null);

        $this->streamProcess($process);
    }

    private function tailIosPhysical(string $appId, string $udid): void
    {
        $this->info("🍎 Tailing iOS Physical Device logs — app: {$appId}");
        $this->info("📱 Device UDID: {$udid}");
        $this->line("Press Ctrl+C to stop...\n");

        // Try idevicesyslog (libimobiledevice) — filter by app bundle ID
        $idevicesyslog = trim(shell_exec('which idevicesyslog 2>/dev/null') ?: '');

        if (! empty($idevicesyslog)) {
            $this->line("Using idevicesyslog (filtering for {$appId})...\n");

            $process = new Process([
                'idevicesyslog',
                '-u', $udid,
                '--match', $appId,
            ]);
            $process->setTimeout(null);
            $this->streamProcess($process);

            return;
        }

        // Fallback: xcrun devicectl stream logs (Xcode 15+)
        $this->line("idevicesyslog not found. Falling back to xcrun devicectl...\n");
        $this->line('<comment>Tip: Install libimobiledevice for better log filtering:</comment>');
        $this->line("  brew install libimobiledevice\n");

        $process = new Process([
            'xcrun', 'devicectl',
            'device', 'info', 'files',
            '--device', $udid,
            '--bundle-id', $appId,
        ]);
        $process->setTimeout(null);
        $this->streamProcess($process);
    }

    // ──────────────────────────────────────────────────────────────────────
    // HELPERS
    // ──────────────────────────────────────────────────────────────────────

    private function streamProcess(Process $process): void
    {
        try {
            $process->start();

            foreach ($process as $type => $data) {
                if ($process::OUT === $type) {
                    $this->line(rtrim($data));
                } else {
                    $this->warn(rtrim($data));
                }
            }
        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            $this->line('Make sure the device is connected and the app is running.');
        }
    }
}
