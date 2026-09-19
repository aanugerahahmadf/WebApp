<?php

namespace Tests\Unit;

use App\Enums\PlatformMode\PlatformMode;
use App\Support\Platform\EnvironmentManager\EnvironmentManager;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * Property Test: Environment Variable Merge Precedence
 *
 * Property 5: For ANY environment variable key that exists in both the base .env file
 * and a platform-specific .env.{mode} file, the Environment Manager SHALL use the value
 * from the platform-specific file when that platform mode is active.
 *
 * Validates: Requirements 3.5
 */
class EnvironmentManagerTest extends TestCase
{
    private EnvironmentManager $manager;

    private array $originalEnv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new EnvironmentManager;

        // Backup original environment
        $this->originalEnv = $_ENV;
    }

    protected function tearDown(): void
    {
        // Restore original environment
        $_ENV = $this->originalEnv;

        // Clean up test files
        $this->cleanupTestFiles();

        parent::tearDown();
    }

    /**
     * Test that platform-specific values override base values
     */
    public function test_platform_values_override_base_values(): void
    {
        $testCases = [
            'APP_NAME' => ['base' => 'BaseApp', 'platform' => 'PlatformApp'],
            'APP_URL' => ['base' => 'http://localhost', 'platform' => 'http://platform.test'],
            'SESSION_DRIVER' => ['base' => 'file', 'platform' => 'database'],
            'CACHE_DRIVER' => ['base' => 'file', 'platform' => 'redis'],
        ];

        foreach ($testCases as $key => $values) {
            // Set base value
            $_ENV[$key] = $values['base'];
            $_SERVER[$key] = $values['base'];
            putenv("{$key}={$values['base']}");

            // Create platform env file
            $platformEnvPath = $this->createPlatformEnvFile(PlatformMode::Web, [
                $key => $values['platform'],
            ]);

            Log::shouldReceive('info')->once();

            // Load platform environment
            $this->manager->loadPlatformEnvironment(PlatformMode::Web);

            // Verify platform value overrides base value
            $this->assertSame(
                $values['platform'],
                $_ENV[$key],
                "Platform value should override base value in \$_ENV for {$key}"
            );

            $this->assertSame(
                $values['platform'],
                $_SERVER[$key],
                "Platform value should override base value in \$_SERVER for {$key}"
            );

            $this->assertSame(
                $values['platform'],
                getenv($key),
                "Platform value should override base value in getenv() for {$key}"
            );

            // Cleanup
            unlink($platformEnvPath);
        }
    }

    /**
     * Test merge precedence across all three platform modes
     */
    public function test_merge_precedence_across_all_platform_modes(): void
    {
        $modes = [
            PlatformMode::Web,
            PlatformMode::Mobile,
            PlatformMode::Desktop,
        ];

        $testKey = 'PLATFORM_TEST_VAR';
        $baseValue = 'base_value';

        Log::shouldReceive('info')->times(count($modes));

        foreach ($modes as $mode) {
            // Set base value
            $_ENV[$testKey] = $baseValue;
            $_SERVER[$testKey] = $baseValue;
            putenv("{$testKey}={$baseValue}");

            $platformValue = "platform_{$mode->value}_value";

            // Create platform-specific env file
            $platformEnvPath = $this->createPlatformEnvFile($mode, [
                $testKey => $platformValue,
            ]);

            // Load platform environment
            $this->manager->loadPlatformEnvironment($mode);

            // Verify platform value overrides base value
            $this->assertSame(
                $platformValue,
                $_ENV[$testKey],
                "Platform value should override base for mode {$mode->value}"
            );

            // Cleanup
            unlink($platformEnvPath);
        }
    }

    /**
     * Test that multiple conflicting variables are all overridden
     */
    public function test_multiple_conflicts_are_all_overridden(): void
    {
        $conflicts = [
            'VAR1' => ['base' => 'base1', 'platform' => 'platform1'],
            'VAR2' => ['base' => 'base2', 'platform' => 'platform2'],
            'VAR3' => ['base' => 'base3', 'platform' => 'platform3'],
            'VAR4' => ['base' => 'base4', 'platform' => 'platform4'],
            'VAR5' => ['base' => 'base5', 'platform' => 'platform5'],
        ];

        // Set all base values
        foreach ($conflicts as $key => $values) {
            $_ENV[$key] = $values['base'];
            $_SERVER[$key] = $values['base'];
            putenv("{$key}={$values['base']}");
        }

        // Create platform env file with all overrides
        $platformVars = array_map(fn ($v) => $v['platform'], $conflicts);
        $platformEnvPath = $this->createPlatformEnvFile(PlatformMode::Web, $platformVars);

        Log::shouldReceive('info')->once()->withArgs(function ($message, $context) {
            return $message === 'Loaded platform environment'
                && $context['conflicts_resolved'] === 5;
        });

        // Load platform environment
        $this->manager->loadPlatformEnvironment(PlatformMode::Web);

        // Verify all conflicts are resolved with platform values
        foreach ($conflicts as $key => $values) {
            $this->assertSame(
                $values['platform'],
                $_ENV[$key],
                "Platform value should override base for {$key}"
            );
        }

        // Cleanup
        unlink($platformEnvPath);
    }

    /**
     * Test that non-conflicting variables from both files are preserved
     */
    public function test_non_conflicting_variables_are_preserved(): void
    {
        // Set base-only variables
        $_ENV['BASE_ONLY_VAR1'] = 'base_value1';
        $_ENV['BASE_ONLY_VAR2'] = 'base_value2';

        // Create platform env file with platform-only variables
        $platformEnvPath = $this->createPlatformEnvFile(PlatformMode::Web, [
            'PLATFORM_ONLY_VAR1' => 'platform_value1',
            'PLATFORM_ONLY_VAR2' => 'platform_value2',
        ]);

        Log::shouldReceive('info')->once();

        // Load platform environment
        $this->manager->loadPlatformEnvironment(PlatformMode::Web);

        // Verify base-only variables are preserved
        $this->assertSame('base_value1', $_ENV['BASE_ONLY_VAR1']);
        $this->assertSame('base_value2', $_ENV['BASE_ONLY_VAR2']);

        // Verify platform-only variables are added
        $this->assertSame('platform_value1', $_ENV['PLATFORM_ONLY_VAR1']);
        $this->assertSame('platform_value2', $_ENV['PLATFORM_ONLY_VAR2']);

        // Cleanup
        unlink($platformEnvPath);
    }

    /**
     * Test that empty platform values override base values
     */
    public function test_empty_platform_values_override_base_values(): void
    {
        $testKey = 'TEST_EMPTY_VAR';

        // Set base value
        $_ENV[$testKey] = 'base_value';
        $_SERVER[$testKey] = 'base_value';
        putenv("{$testKey}=base_value");

        // Create platform env file with empty value
        $platformEnvPath = $this->createPlatformEnvFile(PlatformMode::Web, [
            $testKey => '',
        ]);

        Log::shouldReceive('info')->once();

        // Load platform environment
        $this->manager->loadPlatformEnvironment(PlatformMode::Web);

        // Verify empty platform value overrides base value
        $this->assertSame(
            '',
            $_ENV[$testKey],
            'Empty platform value should override base value'
        );

        // Cleanup
        unlink($platformEnvPath);
    }

    /**
     * Test quoted values in platform files
     */
    public function test_quoted_platform_values_override_correctly(): void
    {
        $testKey = 'TEST_QUOTED_VAR';

        // Set base value
        $_ENV[$testKey] = 'base_value';

        // Create platform env file with quoted value
        $platformEnvContent = "{$testKey}=\"platform value with spaces\"";
        $platformEnvPath = base_path('.env.web');
        file_put_contents($platformEnvPath, $platformEnvContent);

        Log::shouldReceive('info')->once();

        // Load platform environment
        $this->manager->loadPlatformEnvironment(PlatformMode::Web);

        // Verify quoted value is properly parsed and overrides base
        $this->assertSame(
            'platform value with spaces',
            $_ENV[$testKey],
            'Quoted platform value should override base value with quotes removed'
        );

        // Cleanup
        unlink($platformEnvPath);
    }

    /**
     * Test that missing platform env file doesn't affect existing variables
     */
    public function test_missing_platform_file_preserves_base_values(): void
    {
        $testKey = 'TEST_PRESERVE_VAR';
        $baseValue = 'base_value';

        // Set base value
        $_ENV[$testKey] = $baseValue;
        $_SERVER[$testKey] = $baseValue;
        putenv("{$testKey}={$baseValue}");

        Log::shouldReceive('debug')->once()->withArgs(function ($message, $context) {
            return str_contains($message, 'Platform environment file not found');
        });

        // Try to load non-existent platform environment
        $this->manager->loadPlatformEnvironment(PlatformMode::Web);

        // Verify base value is preserved
        $this->assertSame(
            $baseValue,
            $_ENV[$testKey],
            'Base value should be preserved when platform file is missing'
        );
    }

    /**
     * Helper: Create a temporary platform environment file
     */
    private function createPlatformEnvFile(PlatformMode $mode, array $variables): string
    {
        $path = base_path($mode->environmentFile());
        $content = '';

        foreach ($variables as $key => $value) {
            $content .= "{$key}={$value}\n";
        }

        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Helper: Clean up test environment files
     */
    private function cleanupTestFiles(): void
    {
        $files = [
            base_path('.env.web'),
            base_path('.env.mobile'),
            base_path('.env.desktop'),
        ];

        foreach ($files as $file) {
            if (file_exists($file)) {
                unlink($file);
            }
        }
    }
}
