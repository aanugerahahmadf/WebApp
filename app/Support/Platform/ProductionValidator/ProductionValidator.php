<?php

namespace App\Support\Platform\ProductionValidator;

use App\Enums\PlatformMode\PlatformMode;
use Illuminate\Support\Facades\Log;

/**
 * ProductionValidator
 *
 * Validates that all required environment variables are present and non-empty
 * before the application starts in production mode.
 *
 * When validation fails in Production_Mode:
 * - All missing variables are logged as a single error entry
 * - A RuntimeException is thrown to prevent the application from starting
 *
 * Requirements: 12.4, 12.7, 12.8
 */
class ProductionValidator
{
    /**
     * Environment variables required by every platform mode.
     *
     * @var string[]
     */
    private const COMMON_REQUIRED = [
        'APP_KEY',
        'APP_URL',
        'APP_ENV',
        'DB_CONNECTION',
        'DB_HOST',
        'DB_DATABASE',
        'DB_USERNAME',
    ];

    /**
     * Additional environment variables required per platform mode.
     *
     * @var array<string, string[]>
     */
    private const PLATFORM_REQUIRED = [
        PlatformMode::Web->value => [
            'SESSION_DRIVER',
        ],
        PlatformMode::Mobile->value => [
            'SESSION_DRIVER',
            'NATIVEPHP_APP_ID',
            'NATIVEPHP_APP_VERSION',
            'VITE_PLATFORM',
        ],
        PlatformMode::Desktop->value => [
            'SESSION_DRIVER',
            'NATIVEPHP_APP_ID',
            'NATIVEPHP_APP_VERSION',
            'NATIVEPHP_HTTP_PORT',
            'VITE_PLATFORM',
        ],
    ];

    /**
     * Validate environment variables for the given platform mode.
     *
     * In production (`APP_ENV=production`), any missing required variable causes
     * an exception that prevents application startup. In non-production environments
     * missing variables are only logged as a warning.
     *
     * @param  PlatformMode  $mode  The active platform mode.
     * @param  bool  $strict  When true, throw even in non-production environments.
     *
     * @throws \RuntimeException When required variables are missing in production mode.
     */
    public function validateDependencies(PlatformMode $mode, bool $strict = false): void
    {
        $missing = $this->getMissingVariables($mode);

        if (empty($missing)) {
            return;
        }

        $isProduction = app()->environment('production') || env('APP_ENV') === 'production';
        $isTesting = app()->environment('testing') || env('APP_ENV') === 'testing';

        $context = [
            'mode' => $mode->label(),
            'missing' => $missing,
        ];

        if ($isProduction || $strict) {
            Log::error('Production startup validation failed: required environment variables are missing.', $context);

            throw new \RuntimeException(
                $this->buildErrorMessage($mode, $missing)
            );
        }

        // Non-production, non-testing: warn but continue
        if (! $isTesting) {
            Log::warning('Missing recommended environment variables for platform mode.', $context);
        }
    }

    /**
     * Return the list of missing required environment variables for the given mode.
     *
     * @return string[] Names of missing variables (empty array when all present).
     */
    public function getMissingVariables(PlatformMode $mode): array
    {
        $required = array_merge(
            self::COMMON_REQUIRED,
            self::PLATFORM_REQUIRED[$mode->value] ?? []
        );

        $missing = [];

        foreach ($required as $variable) {
            $value = env($variable) ?? ($_ENV[$variable] ?? null);

            if ($value === null || trim((string) $value) === '') {
                $missing[] = $variable;
            }
        }

        return $missing;
    }

    /**
     * Check whether the environment is valid for the given platform mode.
     *
     * Returns true when all required variables are present, false otherwise.
     */
    public function isValid(PlatformMode $mode): bool
    {
        return empty($this->getMissingVariables($mode));
    }

    /**
     * Return all required variable names for the given platform mode.
     *
     * @return string[]
     */
    public function getRequiredVariables(PlatformMode $mode): array
    {
        return array_merge(
            self::COMMON_REQUIRED,
            self::PLATFORM_REQUIRED[$mode->value] ?? []
        );
    }

    /**
     * Build a human-readable error message listing all missing variables.
     *
     * @param  string[]  $missing
     */
    private function buildErrorMessage(PlatformMode $mode, array $missing): string
    {
        $lines = [
            "Production startup validation failed for platform mode: {$mode->label()}",
            '',
            'The following required environment variables are missing or empty:',
        ];

        foreach ($missing as $variable) {
            $lines[] = "  - {$variable}";
        }

        $lines[] = '';
        $lines[] = 'Set these variables in your .env file or the platform-specific override';
        $lines[] = "file ({$mode->environmentFile()}) before starting the application.";

        return implode(PHP_EOL, $lines);
    }
}
