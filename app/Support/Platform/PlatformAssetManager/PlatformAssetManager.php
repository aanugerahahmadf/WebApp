<?php

namespace App\Support\Platform\PlatformAssetManager;

use App\Enums\PlatformMode\PlatformMode;

/**
 * PlatformAssetManager
 *
 * Manages platform-specific asset compilation and serving.
 * Handles Vite manifest loading and asset URL resolution for web, mobile, and desktop platforms.
 *
 * Responsibilities:
 * - Configure asset paths based on platform mode
 * - Return platform-specific manifest paths (build/web, build/mobile, build/desktop)
 * - Return platform-specific Vite entry points
 * - Resolve versioned asset URLs from manifest
 * - Load and parse manifest.json files
 *
 * Requirements: 4.1, 4.2, 4.3, 4.4, 4.5
 */
class PlatformAssetManager
{
    /**
     * The current platform mode
     */
    private ?PlatformMode $mode = null;

    /**
     * Cached manifest data
     */
    private ?array $manifest = null;

    /**
     * Configure the asset manager for a specific platform mode.
     *
     * @param  PlatformMode  $mode  The platform mode to configure for
     */
    public function configure(PlatformMode $mode): void
    {
        $this->mode = $mode;
        $this->manifest = null; // Clear cache when mode changes
    }

    /**
     * Get the platform-specific asset manifest path.
     *
     * Returns the path to the manifest.json file that contains
     * the mapping of source files to compiled assets.
     *
     * @return string Absolute path to the manifest file
     */
    public function getManifestPath(): string
    {
        $buildDir = $this->getBuildDirectory();

        return public_path("{$buildDir}/manifest.json");
    }

    /**
     * Get the platform-specific build directory.
     *
     * Returns the directory where compiled assets are stored:
     * - build/web for Web mode
     * - build/mobile for Mobile mode
     * - build/desktop for Desktop mode
     *
     * @return string Relative path to build directory (e.g., "build/web")
     */
    public function getBuildDirectory(): string
    {
        if ($this->mode === null) {
            // Default to web if not configured
            return 'build/web';
        }

        return $this->mode->assetDirectory();
    }

    /**
     * Get the platform-specific Vite entry point.
     *
     * Returns the JavaScript file that serves as the entry point
     * for Vite compilation for this platform.
     *
     * @return string Path to the Vite entry point (e.g., "resources/js/app-web/app-web.js")
     */
    public function getViteInput(): string
    {
        if ($this->mode === null) {
            // Default to web if not configured
            return 'resources/js/app-web/app-web.js';
        }

        return $this->mode->viteInput();
    }

    /**
     * Resolve an asset path to its versioned URL.
     *
     * Looks up the asset in the manifest and returns the versioned URL.
     * Falls back to the original path if manifest is not found or asset is not in manifest.
     *
     * @param  string  $path  The source asset path (e.g., "resources/js/app/app.js")
     * @return string The versioned asset URL
     */
    public function asset(string $path): string
    {
        $manifest = $this->loadManifest();

        // Check if asset exists in manifest
        if (isset($manifest[$path]['file'])) {
            $buildDir = $this->getBuildDirectory();

            return asset("{$buildDir}/{$manifest[$path]['file']}");
        }

        // Fallback to original path if not in manifest
        return asset($path);
    }

    /**
     * Get CSS files associated with an entry point.
     *
     * Returns an array of CSS file URLs that are imported by the given entry point.
     *
     * @param  string  $entryPoint  The entry point path (e.g., "resources/js/app-web/app-web.js")
     * @return array<string> Array of CSS file URLs
     */
    public function getEntryPointCss(string $entryPoint): array
    {
        $manifest = $this->loadManifest();

        if (! isset($manifest[$entryPoint]['css'])) {
            return [];
        }

        $buildDir = $this->getBuildDirectory();
        $cssFiles = [];

        foreach ($manifest[$entryPoint]['css'] as $cssFile) {
            $cssFiles[] = asset("{$buildDir}/{$cssFile}");
        }

        return $cssFiles;
    }

    /**
     * Check if the manifest file exists for the current platform.
     *
     * @return bool True if manifest exists, false otherwise
     */
    public function manifestExists(): bool
    {
        return file_exists($this->getManifestPath());
    }

    /**
     * Get the current platform mode.
     *
     * @return PlatformMode|null The current platform mode, or null if not configured
     */
    public function getMode(): ?PlatformMode
    {
        return $this->mode;
    }

    /**
     * Load and parse the platform-specific manifest.json file.
     *
     * The manifest maps source files to their compiled, versioned counterparts.
     * Results are cached after first load.
     *
     * @return array<string, array> Parsed manifest data
     */
    private function loadManifest(): array
    {
        // Return cached manifest if available
        if ($this->manifest !== null) {
            return $this->manifest;
        }

        $manifestPath = $this->getManifestPath();

        // Check if manifest exists
        if (! file_exists($manifestPath)) {
            $this->manifest = [];

            return $this->manifest;
        }

        // Read and parse manifest
        try {
            $manifestContent = file_get_contents($manifestPath);

            if ($manifestContent === false) {
                $this->manifest = [];

                return $this->manifest;
            }

            $manifest = json_decode($manifestContent, true);

            if ($manifest === null || ! is_array($manifest)) {
                $this->manifest = [];

                return $this->manifest;
            }

            $this->manifest = $manifest;

            return $this->manifest;

        } catch (\Throwable $e) {
            // If any error occurs, return empty manifest
            $this->manifest = [];

            return $this->manifest;
        }
    }

    /**
     * Clear the cached manifest data.
     *
     * Useful for development when manifest is regenerated.
     */
    public function clearManifestCache(): void
    {
        $this->manifest = null;
    }

    /**
     * Get all entry points from the manifest.
     *
     * Returns an array of entry point paths that are marked as entry points in the manifest.
     *
     * @return array<string> Array of entry point paths
     */
    public function getEntryPoints(): array
    {
        $manifest = $this->loadManifest();
        $entryPoints = [];

        foreach ($manifest as $path => $data) {
            if (isset($data['isEntry']) && $data['isEntry'] === true) {
                $entryPoints[] = $path;
            }
        }

        return $entryPoints;
    }

    /**
     * Get the full manifest data.
     *
     * Useful for debugging or advanced use cases.
     *
     * @return array<string, array> Complete manifest data
     */
    public function getManifest(): array
    {
        return $this->loadManifest();
    }
}
