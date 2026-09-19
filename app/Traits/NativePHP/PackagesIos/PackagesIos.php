<?php

namespace App\Traits\NativePHP\PackagesIos;

use Illuminate\Support\Facades\Process;

/**
 * Local override of Native\Mobile\Traits\PackagesIos.
 *
 * Changes vs vendor:
 *  - prepareBuildEnvironment: timeout(300) → timeout(0) for xcodebuild resolvePackageDependencies
 *  - exportArchiveWithXcode:  timeout(600) → timeout(0) for xcodebuild exportArchive
 *  - uploadToAppStore:        timeout(600) → timeout(0) for xcrun altool upload
 */
trait PackagesIos
{
    /**
     * Override: resolve package dependencies with no timeout.
     */
    protected function prepareBuildEnvironment(): bool
    {
        $iosPath = base_path('nativephp/ios');

        $shouldCleanCaches = $this->option('clean-caches') ||
                             getenv('CI') === 'true' ||
                             getenv('GITHUB_ACTIONS') === 'true';

        if ($shouldCleanCaches) {
            $derivedDataPath = $_SERVER['HOME'].'/Library/Developer/Xcode/DerivedData';
            if (is_dir($derivedDataPath)) {
                Process::run(['rm', '-rf', $derivedDataPath]);
            }

            $spmCachePaths = [
                $_SERVER['HOME'].'/Library/Caches/org.swift.swiftpm',
                $_SERVER['HOME'].'/Library/org.swift.swiftpm',
            ];

            foreach ($spmCachePaths as $cachePath) {
                if (is_dir($cachePath)) {
                    Process::run(['rm', '-rf', $cachePath]);
                }
            }

            $result = Process::path($iosPath)
                ->timeout(0)   // ← no timeout (was 300s)
                ->run(['xcodebuild', '-resolvePackageDependencies']);

            if (! $result->successful()) {
                \Laravel\Prompts\error('Failed to resolve package dependencies');
                $this->newLine();
                $this->line('<fg=red>Error output:</>');
                $this->line($result->errorOutput() ?: $result->output());
                $this->newLine();

                return false;
            }
        }

        return true;
    }

    /**
     * Override: export archive with no timeout.
     */
    protected function exportArchiveWithXcode(string $archivePath): ?string
    {
        $basePath = base_path('nativephp/ios');
        $exportPath = $basePath.'/build/export';
        $exportOptionsPath = $this->createExportOptions($basePath);

        if (is_dir($exportPath)) {
            Process::run('rm -rf '.escapeshellarg($exportPath));
        }

        $result = Process::path($basePath)
            ->timeout(0)   // ← no timeout (was 600s)
            ->run([
                'xcodebuild',
                '-exportArchive',
                '-archivePath', $archivePath,
                '-exportPath', $exportPath,
                '-exportOptionsPlist', $exportOptionsPath,
            ]);

        if (! $result->successful()) {
            \Laravel\Prompts\error('IPA export failed');
            $this->newLine();
            $this->line('<fg=red>Export error output:</>');
            $this->line($result->errorOutput() ?: $result->output());
            $this->newLine();

            return null;
        }

        $ipaFiles = glob($exportPath.'/*.ipa');
        if (empty($ipaFiles)) {
            \Laravel\Prompts\error('No IPA file was generated');

            return null;
        }

        $ipaPath = $ipaFiles[0];

        if (! $this->verifyIpaCodeSignature($ipaPath)) {
            \Laravel\Prompts\error('IPA verification failed - unsigned executables detected');

            return null;
        }

        return $ipaPath;
    }

    /**
     * Override: upload to App Store with no timeout.
     */
    protected function uploadToAppStore(string $ipaPath, ?array $iosSigningConfig = null): void
    {
        if ($iosSigningConfig) {
            $apiKey = $this->resolveApiKeyFromPath($iosSigningConfig['apiKeyPath']);
            $apiKeyId = $iosSigningConfig['apiKeyId'];
            $apiIssuerId = $iosSigningConfig['apiIssuerId'];
        } else {
            $apiKey = $this->getAppStoreApiKey();
            $apiKeyId = config('nativephp.app_store_connect.api_key_id');
            $apiIssuerId = config('nativephp.app_store_connect.api_issuer_id');
        }

        if (! $apiKey || ! $apiKeyId || ! $apiIssuerId) {
            \Laravel\Prompts\error('App Store Connect API credentials not configured');

            return;
        }

        $tempApiKeyPath = $this->createTemporaryApiKeyFile($apiKey, $apiKeyId);
        if (! $tempApiKeyPath) {
            return;
        }

        try {
            $command = [
                'xcrun', 'altool',
                '--upload-app',
                '-f', $ipaPath,
                '-t', 'ios',
                '--apiKey', $apiKeyId,
                '--apiIssuer', $apiIssuerId,
                '--output-format', 'json',
            ];

            $result = Process::timeout(0)   // ← no timeout (was 600s)
                ->env(['API_PRIVATE_KEYS_DIR' => dirname($tempApiKeyPath)])
                ->run($command);

            $stdout = $result->output();
            $stderr = $result->errorOutput();
            $allOutput = $stdout."\n".$stderr;
            $jsonResponse = $stdout ? json_decode($stdout, true) : null;

            $hasProductErrors = $jsonResponse && ! empty($jsonResponse['product-errors']);
            $hasFailureInOutput = str_contains($allOutput, 'Failed to upload package')
                || str_contains($allOutput, 'Upload failed')
                || str_contains($allOutput, 'ERROR ITMS-')
                || str_contains($allOutput, 'error uploading');

            if ($result->successful() && ! $hasProductErrors && ! $hasFailureInOutput) {
                \Laravel\Prompts\info('Successfully uploaded to App Store Connect');
            } else {
                \Laravel\Prompts\error('Upload to App Store Connect failed');

                if ($hasProductErrors) {
                    foreach ($jsonResponse['product-errors'] as $error) {
                        $this->newLine();
                        $this->line('<fg=red>Error: '.($error['message'] ?? 'Unknown error').'</>');
                        $reason = $error['user-info']['NSLocalizedFailureReason'] ?? null;
                        if ($reason) {
                            $this->line('<fg=yellow>'.$reason.'</>');
                        }
                    }
                }

                if ($stderr) {
                    $this->newLine();
                    $this->line('<fg=yellow>stderr:</>');
                    $this->line($stderr);
                }
                if ($stdout) {
                    $this->newLine();
                    $this->line('<fg=yellow>stdout:</>');
                    $this->line($stdout);
                }
            }
        } finally {
            if (file_exists($tempApiKeyPath)) {
                unlink($tempApiKeyPath);
            }
        }
    }
}
