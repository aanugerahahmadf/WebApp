<?php

namespace App\Support\Platform\EnvironmentManager;

use App\Enums\PlatformMode\PlatformMode;
use Illuminate\Support\Facades\Log;

/**
 * EnvironmentManager
 *
 * Manages platform-specific environment configuration by loading and merging
 * .env.{platform} files with the base environment variables.
 *
 * Responsibilities:
 * - Load platform-specific .env files (.env.web, .env.mobile, .env.desktop)
 * - Parse environment files in Laravel .env format
 * - Merge platform-specific variables into $_ENV, $_SERVER, and putenv()
 * - Handle missing platform environment files gracefully
 * - Log loaded environment files and variable counts
 *
 * Requirements: 3.1, 3.2, 3.3, 3.4, 3.6, 3.7
 */
class EnvironmentManager
{
    /**
     * Load platform-specific environment file and merge with existing environment.
     *
     * Platform-specific values take precedence over base .env values.
     * Missing platform environment files are handled gracefully with logging.
     *
     * @param  PlatformMode  $mode  The platform mode to load environment for
     */
    public function loadPlatformEnvironment(PlatformMode $mode): void
    {
        $platformEnvFile = base_path($mode->environmentFile());

        if (! file_exists($platformEnvFile)) {
            Log::debug('Platform environment file not found, using base environment', [
                'file' => $mode->environmentFile(),
                'mode' => $mode->value,
            ]);

            return;
        }

        try {
            $platformVars = $this->parseEnvironmentFile($platformEnvFile);

            if (empty($platformVars)) {
                Log::info('Platform environment file is empty', [
                    'file' => $mode->environmentFile(),
                    'mode' => $mode->value,
                ]);

                return;
            }

            // Merge platform-specific variables into runtime environment
            $conflictCount = 0;
            foreach ($platformVars as $key => $value) {
                // Track if we're overriding an existing value
                if (isset($_ENV[$key]) || isset($_SERVER[$key])) {
                    $conflictCount++;
                }

                $_ENV[$key] = $value;
                $_SERVER[$key] = $value;
                putenv("{$key}={$value}");
            }

            Log::info('Loaded platform environment', [
                'file' => $mode->environmentFile(),
                'mode' => $mode->value,
                'vars_count' => count($platformVars),
                'conflicts_resolved' => $conflictCount,
            ]);

        } catch (\Throwable $e) {
            Log::warning('Failed to load platform environment file', [
                'file' => $mode->environmentFile(),
                'mode' => $mode->value,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Parse an environment file in Laravel .env format.
     *
     * Supports:
     * - KEY=value syntax
     * - Comments (lines starting with #)
     * - Empty lines
     * - Quoted values (single and double quotes)
     * - Values with spaces (when quoted)
     *
     * @param  string  $path  Absolute path to the environment file
     * @return array<string, string> Associative array of environment variables
     */
    private function parseEnvironmentFile(string $path): array
    {
        if (! is_readable($path)) {
            throw new \RuntimeException("Environment file is not readable: {$path}");
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        if ($lines === false) {
            throw new \RuntimeException("Failed to read environment file: {$path}");
        }

        $vars = [];

        foreach ($lines as $lineNumber => $line) {
            // Trim whitespace
            $line = trim($line);

            // Skip empty lines and comments
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            // Check if line contains an equals sign
            if (! str_contains($line, '=')) {
                Log::debug('Skipping malformed environment line', [
                    'file' => $path,
                    'line_number' => $lineNumber + 1,
                    'line' => $line,
                ]);

                continue;
            }

            // Split on first equals sign only
            $parts = explode('=', $line, 2);
            $key = trim($parts[0]);
            $value = isset($parts[1]) ? trim($parts[1]) : '';

            // Validate key (must not be empty and should be valid variable name)
            if ($key === '') {
                Log::debug('Skipping line with empty key', [
                    'file' => $path,
                    'line_number' => $lineNumber + 1,
                ]);

                continue;
            }

            // Remove quotes from value if present
            $value = $this->removeQuotes($value);

            // Handle special values
            $value = $this->parseSpecialValues($value);

            $vars[$key] = $value;
        }

        return $vars;
    }

    /**
     * Remove surrounding quotes from a value.
     *
     * Handles both single and double quotes.
     * Only removes quotes if they match at start and end.
     *
     * @param  string  $value  The value to process
     * @return string The value without surrounding quotes
     */
    private function removeQuotes(string $value): string
    {
        $value = trim($value);

        // Check for double quotes
        if (strlen($value) >= 2 &&
            str_starts_with($value, '"') &&
            str_ends_with($value, '"')) {
            return substr($value, 1, -1);
        }

        // Check for single quotes
        if (strlen($value) >= 2 &&
            str_starts_with($value, "'") &&
            str_ends_with($value, "'")) {
            return substr($value, 1, -1);
        }

        return $value;
    }

    /**
     * Parse special environment values like null, true, false, empty.
     *
     * Converts string representations to their actual values:
     * - "(null)" -> ""
     * - "null" -> ""
     * - "(empty)" -> ""
     * - "empty" -> ""
     * - "true" -> "true" (as string, not boolean)
     * - "false" -> "false" (as string, not boolean)
     *
     * Note: We keep booleans as strings because environment variables are strings.
     *
     * @param  string  $value  The value to parse
     * @return string The parsed value
     */
    private function parseSpecialValues(string $value): string
    {
        $lowerValue = strtolower($value);

        // Handle null and empty values
        if (in_array($lowerValue, ['(null)', 'null', '(empty)', 'empty'], true)) {
            return '';
        }

        return $value;
    }

    /**
     * Get the currently loaded environment variables for debugging.
     *
     * @return array<string, string> Current environment variables
     */
    public function getCurrentEnvironment(): array
    {
        return $_ENV;
    }

    /**
     * Check if a platform-specific environment file exists.
     *
     * @param  PlatformMode  $mode  The platform mode to check
     * @return bool True if the platform environment file exists
     */
    public function platformEnvironmentExists(PlatformMode $mode): bool
    {
        return file_exists(base_path($mode->environmentFile()));
    }
}
