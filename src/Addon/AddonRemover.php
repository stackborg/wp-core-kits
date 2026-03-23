<?php

/**
 * AddonRemover - deactivates, cleans up, and removes an addon.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Addon;

use Stackborg\WPCoreKits\Contracts\AddonInterface;

/**
 * AddonRemover — deactivates, cleans up, and removes an addon.
 *
 * Handles the full uninstall flow:
 * 1. Deactivate the addon if active
 * 2. Call the addon's cleanup() hook (DB tables, options)
 * 3. Delete the addon directory
 * 4. Remove from registry state
 */
class AddonRemover
{
    public function __construct(
        private readonly AddonRegistry $registry,
        private readonly string $addonsDir,
    ) {
    }

    /**
     * Fully remove an addon — deactivate, cleanup, delete files.
     */
    public function uninstall(string $slug): InstallResult
    {
        if (!$this->registry->isInstalled($slug)) {
            return InstallResult::fail("Addon '{$slug}' is not installed");
        }

        // Step 1: Deactivate if active
        if ($this->registry->isActive($slug)) {
            $this->registry->deactivate($slug);
        }

        // Step 2: Call cleanup hook if addon instance is registered
        $addon = $this->registry->get($slug);
        if ($addon instanceof AddonInterface) {
            try {
                $addon->cleanup();
            } catch (\Throwable $e) {
                // Cleanup failure should not block uninstall
                // Log in production environment
            }
        }

        // Step 3: Delete addon directory
        $addonDir = rtrim($this->addonsDir, '/') . '/' . $slug;
        if (is_dir($addonDir)) {
            $this->removeDirectory($addonDir);
        }

        // Step 4: Remove from registry state
        $this->registry->remove($slug);

        return InstallResult::ok("Addon '{$slug}' uninstalled successfully", null);
    }

    /**
     * Recursively remove a directory.
     */
    private function removeDirectory(string $dir): bool
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
