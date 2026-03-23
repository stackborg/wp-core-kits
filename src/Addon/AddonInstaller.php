<?php

/**
 * AddonInstaller - downloads, verifies, and installs addon modules.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Addon;

/**
 * AddonInstaller — downloads, verifies, and installs addon modules.
 *
 * Handles the full install flow:
 * 1. Download zip from URL (with license header if paid)
 * 2. Verify file integrity (SHA256 checksum)
 * 3. Validate addon.json exists and is well-formed
 * 4. Check version compatibility
 * 5. Extract to addons/{slug}/ directory
 * 6. Register in AddonRegistry
 * 7. Auto-activate
 */
class AddonInstaller
{
    public function __construct(
        private readonly AddonRegistry $registry,
        private readonly string $addonsDir,
        private readonly string $coreVersion,
        private readonly string $uiVersion,
        private readonly ?string $pluginVersion = null,
    ) {
    }

    /**
     * Install an addon from a remote zip URL.
     *
     * @param string      $zipUrl     URL to download the addon zip from
     * @param string|null $checksum   Expected SHA256 checksum (null to skip)
     * @param string|null $licenseKey License key for paid addons (sent in header)
     */
    public function install(string $zipUrl, ?string $checksum = null, ?string $licenseKey = null): InstallResult
    {
        // Step 1: Download to temp file
        $tmpFile = $this->download($zipUrl, $licenseKey);
        if ($tmpFile === null) {
            return InstallResult::fail('Failed to download addon from: ' . $zipUrl);
        }

        try {
            // Step 2: Verify checksum if provided
            if ($checksum !== null) {
                $fileHash = hash_file('sha256', $tmpFile);
                if (!hash_equals($checksum, $fileHash)) {
                    return InstallResult::fail('Checksum verification failed — file may be tampered');
                }
            }

            // Step 3: Extract to temp dir and validate
            $extractDir = $this->extractZip($tmpFile);
            if ($extractDir === null) {
                return InstallResult::fail('Failed to extract addon zip');
            }

            try {
                // Step 4: Read and validate addon.json
                $meta = $this->findAddonMeta($extractDir);
                if ($meta === null) {
                    return InstallResult::fail('No valid addon.json found in zip');
                }

                // Step 5: Check version compatibility
                $compat = VersionResolver::checkAddonCompatibility(
                    $meta,
                    $this->coreVersion,
                    $this->uiVersion,
                    pluginVersion: $this->pluginVersion,
                    addonVersionResolver: VersionResolver::addonResolver($this->registry),
                );
                if (!$compat->compatible) {
                    return InstallResult::fail(
                        'Addon is not compatible with your environment',
                        $compat->errors
                    );
                }

                // Step 6: Move to final addons directory
                $targetDir = rtrim($this->addonsDir, '/') . '/' . $meta->slug;

                // Remove existing if reinstalling
                if (is_dir($targetDir)) {
                    $this->removeDirectory($targetDir);
                }

                $moved = $this->moveDirectory($extractDir, $targetDir);
                if (!$moved) {
                    return InstallResult::fail('Failed to move addon to addons directory');
                }

                // Step 7: Register and auto-activate
                $this->registry->scan($this->addonsDir);
                $this->registry->activate($meta->slug);

                return InstallResult::ok('Addon installed and activated successfully', $meta);
            } finally {
                // Cleanup extracted temp dir if it still exists
                if (is_dir($extractDir)) {
                    $this->removeDirectory($extractDir);
                }
            }
        } finally {
            // Cleanup downloaded temp file
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    /**
     * Install from a local zip file (for testing or manual installs).
     */
    public function installFromZip(string $zipPath, ?string $checksum = null): InstallResult
    {
        if (!file_exists($zipPath)) {
            return InstallResult::fail('Zip file not found: ' . $zipPath);
        }

        // Verify checksum if provided
        if ($checksum !== null) {
            $fileHash = hash_file('sha256', $zipPath);
            if (!hash_equals($checksum, $fileHash)) {
                return InstallResult::fail('Checksum verification failed');
            }
        }

        $extractDir = $this->extractZip($zipPath);
        if ($extractDir === null) {
            return InstallResult::fail('Failed to extract addon zip');
        }

        try {
            $meta = $this->findAddonMeta($extractDir);
            if ($meta === null) {
                return InstallResult::fail('No valid addon.json found in zip');
            }

            $compat = VersionResolver::checkAddonCompatibility(
                $meta,
                $this->coreVersion,
                $this->uiVersion,
                pluginVersion: $this->pluginVersion,
                addonVersionResolver: VersionResolver::addonResolver($this->registry),
            );
            if (!$compat->compatible) {
                return InstallResult::fail('Addon incompatible', $compat->errors);
            }

            $targetDir = rtrim($this->addonsDir, '/') . '/' . $meta->slug;
            if (is_dir($targetDir)) {
                $this->removeDirectory($targetDir);
            }

            $moved = $this->moveDirectory($extractDir, $targetDir);
            if (!$moved) {
                return InstallResult::fail('Failed to move addon files');
            }

            $this->registry->scan($this->addonsDir);
            $this->registry->activate($meta->slug);

            return InstallResult::ok('Addon installed successfully', $meta);
        } finally {
            if (is_dir($extractDir)) {
                $this->removeDirectory($extractDir);
            }
        }
    }

    /**
     * Download a file from URL to temp directory.
     */
    private function download(string $url, ?string $licenseKey = null): ?string
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'sb_addon_');
        if ($tmpFile === false) {
            return null;
        }

        $context = null;
        if ($licenseKey !== null) {
            // Send license key in authorization header for paid addon downloads
            $context = stream_context_create([
                'http' => [
                    'header' => "Authorization: Bearer {$licenseKey}\r\n",
                    'timeout' => 60,
                ],
            ]);
        }

        $content = @file_get_contents($url, false, $context);
        if ($content === false) {
            @unlink($tmpFile);
            return null;
        }

        file_put_contents($tmpFile, $content);
        return $tmpFile;
    }

    /**
     * Extract a zip file to a temp directory.
     */
    private function extractZip(string $zipPath): ?string
    {
        $extractDir = sys_get_temp_dir() . '/sb_addon_extract_' . uniqid();
        mkdir($extractDir, 0755, true);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) !== true) {
            $this->removeDirectory($extractDir);
            return null;
        }

        $zip->extractTo($extractDir);
        $zip->close();

        return $extractDir;
    }

    /**
     * Find addon.json in extracted directory.
     * Handles both flat extraction and nested directory (common with zip).
     */
    private function findAddonMeta(string $extractDir): ?AddonMeta
    {
        // Check if addon.json is directly in extract dir
        if (file_exists($extractDir . '/addon.json')) {
            try {
                return AddonMeta::fromPath($extractDir);
            } catch (\RuntimeException) {
                return null;
            }
        }

        // Check one level deep (common: zip contains a single dir)
        $subdirs = glob($extractDir . '/*', GLOB_ONLYDIR);
        if ($subdirs === false) {
            return null;
        }

        foreach ($subdirs as $subdir) {
            if (file_exists($subdir . '/addon.json')) {
                try {
                    return AddonMeta::fromPath($subdir);
                } catch (\RuntimeException) {
                    continue;
                }
            }
        }

        return null;
    }

    /**
     * Move a directory (rename or copy+delete).
     */
    private function moveDirectory(string $source, string $target): bool
    {
        // For extracted zips, the actual content might be nested
        // Find the dir containing addon.json
        if (file_exists($source . '/addon.json')) {
            return @rename($source, $target);
        }

        $subdirs = glob($source . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($subdirs as $subdir) {
            if (file_exists($subdir . '/addon.json')) {
                return @rename($subdir, $target);
            }
        }

        return @rename($source, $target);
    }

    /**
     * Recursively remove a directory and all its contents.
     */
    public function removeDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        return rmdir($dir);
    }
}
