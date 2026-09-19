<?php

namespace App\Console\Commands\PlatformStatusCommand;

use App\Enums\PlatformMode\PlatformMode;
use App\Enums\RuntimePlatform\RuntimePlatform;
use App\Support\Platform\PlatformFeatureRegistry\PlatformFeatureRegistry;
use Illuminate\Console\Command;

/**
 * Display a summary of the current platform mode, runtime platform,
 * loaded environment files, asset directories, and available features.
 *
 * Requirements: 8.3, 8.5
 */
class PlatformStatusCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'platform:status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Display the current platform mode, runtime platform, environment file, asset directory, and available features';

    /**
     * Execute the console command.
     */
    public function handle(PlatformFeatureRegistry $registry): int
    {
        /** @var PlatformMode $mode */
        $mode = app('platform.mode');

        /** @var RuntimePlatform $runtimePlatform */
        $runtimePlatform = app('runtime.platform');

        $envFile = $mode->environmentFile();
        $assetDir = $mode->assetDirectory();

        $allFeatures = array_keys($this->allFeatures());
        $availableFeatures = $registry->getAvailableFeatures($runtimePlatform);
        $unavailableFeatures = array_values(array_diff($allFeatures, $availableFeatures));

        $this->line('');
        $this->line('<fg=cyan;options=bold>Platform Status</>');
        $this->line('<fg=cyan>================</>');
        $this->line('');

        $this->line(sprintf(
            '<fg=white>Mode:</>             %s <fg=gray>(%s)</>',
            $mode->label(),
            $mode->value
        ));

        $this->line(sprintf(
            '<fg=white>Runtime Platform:</> %s',
            $runtimePlatform->label()
        ));

        $this->line(sprintf(
            '<fg=white>Environment File:</> %s',
            $envFile
        ));

        $this->line(sprintf(
            '<fg=white>Asset Directory:</>  %s',
            $assetDir
        ));

        $this->line('');

        if (empty($availableFeatures)) {
            $this->line('<fg=yellow>Available Features:</> <fg=gray>(none)</>');
        } else {
            $this->line('<fg=green>Available Features:</>');
            foreach ($availableFeatures as $feature) {
                $this->line("  <fg=green>-</> {$feature}");
            }
        }

        if (! empty($unavailableFeatures)) {
            $this->line('');
            $this->line('<fg=red>Unavailable Features:</>');
            foreach ($unavailableFeatures as $feature) {
                $this->line("  <fg=red>-</> {$feature}");
            }
        }

        $this->line('');

        return self::SUCCESS;
    }

    /**
     * Return the complete feature list from the registry's known features.
     * We derive it by checking each feature against a known platform that
     * supports none (or all) so we can enumerate the full key set.
     *
     * Because PlatformFeatureRegistry::FEATURE_MATRIX is private, we probe
     * each RuntimePlatform case and union the results.
     *
     * @return array<string, true>
     */
    private function allFeatures(): array
    {
        /** @var PlatformFeatureRegistry $registry */
        $registry = app(PlatformFeatureRegistry::class);

        $features = [];
        foreach (RuntimePlatform::cases() as $platform) {
            foreach ($registry->getAvailableFeatures($platform) as $feature) {
                $features[$feature] = true;
            }
        }

        return $features;
    }
}
