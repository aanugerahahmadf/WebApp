<?php

namespace App\Enums\PlatformMode;

enum PlatformMode: string
{
    case Web = 'web';
    case Mobile = 'mobile';
    case Desktop = 'desktop';

    /**
     * Get a human-readable label for the platform mode.
     */
    public function label(): string
    {
        return match ($this) {
            self::Web => 'Web Server',
            self::Mobile => 'Mobile Native',
            self::Desktop => 'Desktop Application',
        };
    }

    /**
     * Get the platform-specific environment file name.
     */
    public function environmentFile(): string
    {
        return ".env.{$this->value}";
    }

    /**
     * Get the platform-specific asset build directory.
     */
    public function assetDirectory(): string
    {
        return "build/{$this->value}";
    }

    /**
     * Get the platform-specific Vite entry point file path.
     */
    public function viteInput(): string
    {
        return "resources/js/app-{$this->value}/app-{$this->value}.js";
    }

    /**
     * Check if this platform mode allows camera access.
     *
     * @return bool True for Mobile and Desktop modes, false for Web
     */
    public function allowsCameraAccess(): bool
    {
        return $this === self::Mobile || $this === self::Desktop;
    }

    /**
     * Check if this platform mode allows file system access.
     *
     * @return bool True for Mobile and Desktop modes, false for Web
     */
    public function allowsFileSystemAccess(): bool
    {
        return $this === self::Mobile || $this === self::Desktop;
    }
}
