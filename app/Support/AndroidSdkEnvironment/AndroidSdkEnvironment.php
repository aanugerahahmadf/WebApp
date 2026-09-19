<?php

namespace App\Support\AndroidSdkEnvironment;

/**
 * Applies Android SDK / AVD environment variables for NativePHP CLI commands.
 *
 * AVDs on this machine live on D: (ANDROID_AVD_HOME), not in %USERPROFILE%\.android\avd.
 */
class AndroidSdkEnvironment
{
    public static function apply(): void
    {
        $sdk = self::normalizePath(
            env('ANDROID_SDK_ROOT')
            ?? env('ANDROID_HOME')
            ?? env('NATIVEPHP_ANDROID_SDK_LOCATION')
            ?? 'D:\\Android\\Sdk'
        );

        $avdHome = self::normalizePath(
            env('ANDROID_AVD_HOME')
            ?? 'D:\\Android\\avd'
        );

        // JAVA_HOME: JDK bundled with Android SDK (used by Gradle to compile Android)
        $javaHome = self::normalizePath(
            env('JAVA_HOME')
            ?? ($sdk ? $sdk.DIRECTORY_SEPARATOR.'jdk-26.0.1' : null)
        );

        // GRADLE_HOME: standalone Gradle installation (e.g. D:\gradle-9.5.1)
        // NATIVEPHP_GRADLE_PATH is the JDK path (used by nativephp config).
        // GRADLE_HOME env var points to the Gradle root directory.
        $gradlePathRaw = self::normalizePath(
            env('GRADLE_HOME')
        );
        // If the path already ends with \bin, derive the home from its parent
        $gradleHome = null;
        $gradleBin = null;
        if ($gradlePathRaw) {
            if (str_ends_with(strtolower($gradlePathRaw), DIRECTORY_SEPARATOR.'bin')
                || str_ends_with(strtolower($gradlePathRaw), '/bin')) {
                $gradleBin = $gradlePathRaw;
                $gradleHome = dirname($gradlePathRaw);
            } else {
                $gradleHome = $gradlePathRaw;
                $gradleBin = $gradlePathRaw.DIRECTORY_SEPARATOR.'bin';
            }
        }

        self::putEnv('ANDROID_SDK_ROOT', $sdk);
        self::putEnv('ANDROID_HOME', $sdk);
        self::putEnv('ANDROID_AVD_HOME', $avdHome);

        if ($javaHome) {
            self::putEnv('JAVA_HOME', $javaHome);
        }

        if ($gradleHome) {
            self::putEnv('GRADLE_HOME', $gradleHome);
        }

        $emulator = self::normalizePath(
            env('ANDROID_EMULATOR')
            ?? config('nativephp.android.emulator_path')
            ?? ($sdk ? $sdk.DIRECTORY_SEPARATOR.'emulator'.DIRECTORY_SEPARATOR.'emulator.exe' : null)
        );

        if ($emulator) {
            self::putEnv('ANDROID_EMULATOR', $emulator);
        }

        self::prependPath(array_filter([
            $sdk ? $sdk.DIRECTORY_SEPARATOR.'platform-tools' : null,
            $sdk ? $sdk.DIRECTORY_SEPARATOR.'emulator' : null,
            $sdk ? $sdk.DIRECTORY_SEPARATOR.'cmdline-tools'.DIRECTORY_SEPARATOR.'latest'.DIRECTORY_SEPARATOR.'bin' : null,
            $javaHome ? $javaHome.DIRECTORY_SEPARATOR.'bin' : null,
            // Gradle bin must come AFTER JDK so `java` resolves to the JDK, not Gradle's wrapper
            $gradleBin,
        ]));
    }

    /**
     * @return list<string>
     */
    public static function listAvds(): array
    {
        self::apply();

        $emulator = env('ANDROID_EMULATOR')
            ?? config('nativephp.android.emulator_path');

        if (! $emulator || ! is_file($emulator)) {
            return [];
        }

        $output = shell_exec('"'.$emulator.'" -list-avds') ?? '';

        return array_values(array_filter(array_map('trim', explode("\n", trim($output)))));
    }

    public static function defaultAvd(): ?string
    {
        $configured = env('NATIVEPHP_ANDROID_AVD');

        if (is_string($configured) && $configured !== '') {
            return $configured;
        }

        $avds = self::listAvds();

        return $avds[0] ?? null;
    }

    private static function normalizePath(?string $path): ?string
    {
        if ($path === null) {
            return null;
        }

        $path = trim($path, " \t\n\r\0\x0B\"'");

        return $path !== '' ? $path : null;
    }

    private static function putEnv(string $key, ?string $value): void
    {
        if ($value === null || $value === '') {
            return;
        }

        putenv("{$key}={$value}");
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }

    /**
     * @param  list<string|null>  $segments
     */
    private static function prependPath(array $segments): void
    {
        $current = getenv('PATH') ?: '';
        $parts = array_filter($segments, fn (?string $segment) => is_string($segment) && $segment !== '' && is_dir($segment));

        if ($parts === []) {
            return;
        }

        $joined = implode(PATH_SEPARATOR, $parts).PATH_SEPARATOR.$current;
        self::putEnv('PATH', $joined);
    }
}
