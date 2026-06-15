<?php

/**
 * FileSystem utility — shared file operations.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Support;

/**
 * FileSystem — shared file system operations.
 *
 * Extracted from AddonInstaller and AddonRemover to avoid
 * code duplication and ensure consistent behavior.
 */
class FileSystem
{
    /**
     * Recursively remove a directory and all its contents.
     *
     * @param string $dir Absolute path to the directory to remove
     * @return bool True if the directory was removed, false on failure
     */
    public static function removeDirectory(string $dir): bool
    {
        if (!is_dir($dir)) {
            return false;
        }

        $items = scandir($dir);
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                self::removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }
}
