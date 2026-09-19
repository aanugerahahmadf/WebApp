<?php

namespace App\Console\Commands\PlatformDetectCommand;

use App\Support\PlatformContext\PlatformContext;
use Illuminate\Console\Command;

class PlatformDetectCommand extends Command
{
    protected $signature = 'platform:detect';

    protected $description = 'Show detected runtime platform (8-target matrix)';

    public function handle(): int
    {
        PlatformContext::reset();

        $platform = PlatformContext::current();

        $this->info('Runtime: '.$platform->label());
        $this->newLine();

        $this->table(['Check', 'Value'], [
            ['Platform key', $platform->value],
            ['Website', $platform->isWebsite() ? 'yes' : 'no'],
            ['Desktop app', $platform->isDesktopApp() ? 'yes' : 'no'],
            ['Mobile app (native)', $platform->isMobileApp() ? 'yes' : 'no'],
            ['Mobile shell UI', $platform->isMobileShell() ? 'yes' : 'no'],
            ['CBIR camera mode', PlatformContext::cbirCameraMode()],
            ['User-Agent', request()->userAgent() ?? '(cli)'],
            ['NATIVEPHP_PLATFORM', env('NATIVEPHP_PLATFORM') ?: '-'],
        ]);

        return self::SUCCESS;
    }
}
