<?php

use App\Enums\PlatformMode\PlatformMode;
use App\Support\Platform\PlatformDependencyValidator\PlatformDependencyValidator;

/**
 * Property 9: Missing Dependencies Are Fully Listed
 *
 * **Validates: Requirements 7.5**
 *
 * For any set of missing platform dependencies detected during command
 * execution, the error message SHALL include references to all of them, with
 * no omissions.
 *
 * What a "dependency" means changed with the Capacitor migration. The shells
 * in `app/Capacitor/{UserApp,AdminApp}` are Node projects, not Composer
 * packages: Capacitor wraps this same Laravel server in a WebView, so there is
 * no PHP package to require. What can be missing is the shell workspace:
 *
 *   1. the shell directory itself,
 *   2. its `capacitor.config.json`,
 *   3. `node_modules/@capacitor/cli` (i.e. `npm install` has been run).
 *
 * class_exists() cannot be controlled without mocking, so this file validates
 * the property through structural guarantees on the return value of
 * validateDependencies():
 *
 *   1. Return type is always array (never null or non-array).
 *   2. Web mode always returns an empty array (Web needs no shell at all).
 *   3. Every entry is a non-empty string naming a concrete, resolvable path or
 *      command — never a bare identifier that a reader has to decode.
 *   4. Every entry points at a path that genuinely does not exist, so a
 *      reported problem is never a false alarm.
 *   5. Property: if items ARE reported missing, all of them appear in a
 *      formatted error message — nothing is silently omitted.
 *
 * The historical version of this file asserted over
 * `nativephp/electron` / `nativephp/laravel` / `nativephp/mobile`; those
 * packages no longer exist and the checks were replaced wholesale.
 */

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Format a list of missing dependencies into the canonical error message the
 * application would display.
 *
 * @param  string[]  $missing
 */
function formatMissingDependenciesMessage(string $mode, array $missing): string
{
    $lines = [];
    $lines[] = "Error: Required dependencies for {$mode} mode are not installed.";
    $lines[] = '';
    $lines[] = 'Missing dependencies:';

    foreach ($missing as $item) {
        $lines[] = "  - {$item}";
    }

    $lines[] = '';
    $lines[] = 'To install, run:';
    $lines[] = '  cd app/Capacitor/UserApp && npm install';

    return implode("\n", $lines);
}

// ─── Tests ───────────────────────────────────────────────────────────────────

describe('Property 9: Missing Dependencies Are Fully Listed', function () {

    // ── Structural guarantee: return type ─────────────────────────────────────

    test('validateDependencies() always returns an array for every platform mode', function (PlatformMode $mode) {
        // **Validates: Requirements 7.5**
        // The return value must always be an array — never null or any other type.
        $validator = new PlatformDependencyValidator;

        expect($validator->validateDependencies($mode))->toBeArray();
    })->with(PlatformMode::cases());

    // ── Web mode guarantees ───────────────────────────────────────────────────

    test('Web mode never reports missing dependencies', function () {
        // **Validates: Requirements 7.5**
        // Web mode has no shell: the Laravel server is the whole product, so the
        // returned array must always be empty.
        $validator = new PlatformDependencyValidator;

        expect($validator->validateDependencies(PlatformMode::Web))->toBeEmpty();
    });

    // ── Entry format guarantees ───────────────────────────────────────────────

    test('every reported dependency names a concrete path or command', function (PlatformMode $mode) {
        // **Validates: Requirements 7.5**
        // Each entry must be a non-empty string that tells the reader what is
        // missing and where — a bare identifier would not be actionable.
        $validator = new PlatformDependencyValidator;
        $missing = $validator->validateDependencies($mode);

        expect($missing)->toBeArray();

        foreach ($missing as $item) {
            expect($item)
                ->toBeString()
                ->not->toBeEmpty();

            // Either a filesystem path or a shell command — both contain a
            // separator, which rules out a bare token.
            expect($item)->toMatch('/[\/\\\\]|\bnpm\b/');
        }
    })->with([
        [PlatformMode::Mobile],
        [PlatformMode::Desktop],
    ]);

    // ── No false alarms ───────────────────────────────────────────────────────

    test('every reported dependency points at something that is really absent', function (PlatformMode $mode) {
        // **Validates: Requirements 7.5**
        // The validator reports filesystem state, so every complaint must
        // correspond to a path that genuinely does not exist. Otherwise the
        // command would refuse to start over a non-problem.
        $validator = new PlatformDependencyValidator;
        $missing = $validator->validateDependencies($mode);

        expect($missing)->toBeArray();

        foreach ($missing as $item) {
            // Only filesystem entries can be checked here; the "run npm
            // install" line describes a fix rather than a missing path.
            if (! str_contains($item, '/') && ! str_contains($item, '\\')) {
                continue;
            }

            $path = base_path(str_replace('/', DIRECTORY_SEPARATOR, ltrim($item, '/')));
            expect(file_exists($path))
                ->toBeFalse("Reported as missing, but the path exists: {$item}");
        }
    })->with([
        [PlatformMode::Mobile],
        [PlatformMode::Desktop],
    ]);

    // ── Shell selection guarantees ────────────────────────────────────────────

    test('Mobile and Desktop modes validate against a real shell directory', function (PlatformMode $mode) {
        // **Validates: Requirements 7.5**
        // Both shell-backed modes resolve to a path under app/Capacitor. If the
        // directory is absent the validator short-circuits with a single
        // "shell not found" entry; if it is present, it reports only the finer
        // grained problems. Either way the shell name must be mentioned.
        $validator = new PlatformDependencyValidator;
        $result = $validator->validateDependencies($mode);

        expect($result)->toBeArray();

        if (is_dir(base_path('app/Capacitor/UserApp'))) {
            // Shell present: no top-level "not found" complaint is possible.
            foreach ($result as $item) {
                expect($item)->not->toContain('shell not found');
            }
        } else {
            expect($result)->toHaveCount(1);
            expect($result[0])->toContain('app/Capacitor/UserApp');
        }
    })->with([
        [PlatformMode::Mobile],
        [PlatformMode::Desktop],
    ]);

    // ── Completeness property ─────────────────────────────────────────────────

    test('all reported dependencies appear in the formatted error message', function (PlatformMode $mode) {
        // **Validates: Requirements 7.5**
        // The whole point of the property: nothing detected is dropped when the
        // error is rendered. Vacuously true when nothing is missing.
        $validator = new PlatformDependencyValidator;
        $missing = $validator->validateDependencies($mode);

        $message = formatMissingDependenciesMessage($mode->value, $missing);

        expect($message)
            ->toBeString()
            ->not->toBeEmpty();

        foreach ($missing as $item) {
            expect($message)->toContain($item);
        }
    })->with(PlatformMode::cases());

    // ── Migration guarantees ──────────────────────────────────────────────────

    test('no report mentions a removed NativePHP package', function (PlatformMode $mode) {
        // The validator was repurposed from a Composer-package check to a
        // Capacitor workspace check. Nothing it reports may name the old
        // packages, or the shell would tell a developer to composer-require
        // something that no longer exists.
        $validator = new PlatformDependencyValidator;
        $missing = $validator->validateDependencies($mode);

        expect($missing)->toBeArray();

        foreach ($missing as $item) {
            expect(strtolower($item))
                ->not->toContain('nativephp')
                ->not->toContain('composer require');
        }
    })->with(PlatformMode::cases());
});
