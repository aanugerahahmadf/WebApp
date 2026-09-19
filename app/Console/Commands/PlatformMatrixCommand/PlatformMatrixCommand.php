<?php

namespace App\Console\Commands\PlatformMatrixCommand;

use App\Enums\RuntimePlatform\RuntimePlatform;
use Illuminate\Console\Command;

class PlatformMatrixCommand extends Command
{
    protected $signature = 'platform:matrix';

    protected $description = 'Show all 8 runtime targets and their CBIR/UI behavior';

    public function handle(): int
    {
        $rows = [];

        foreach (RuntimePlatform::cases() as $platform) {
            $rows[] = [
                $platform->value,
                $platform->label(),
                $platform->isWebsite() ? 'yes' : 'no',
                $platform->isDesktopApp() ? 'yes' : 'no',
                $platform->isMobileApp() ? 'yes' : 'no',
                $platform->isMobileShell() ? 'yes' : 'no',
                $platform->cbirCameraMode(),
            ];
        }

        $this->info('8-target runtime matrix (see docs/PLATFORM_TESTING.md for test steps)');
        $this->newLine();

        $this->table(
            ['Key', 'Label', 'Web', 'Desktop', 'Native app', 'Mobile UI', 'CBIR camera'],
            $rows,
        );

        $this->newLine();
        $this->line('Current request: <comment>php artisan platform:detect</comment>');

        return self::SUCCESS;
    }
}
