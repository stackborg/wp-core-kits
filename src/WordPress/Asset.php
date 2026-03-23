<?php

/**
 * Asset management wrapper.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\WordPress;

use Stackborg\WPCoreKits\Contracts\AssetInterface;

/**
 * Asset management wrapper.
 *
 * Simplifies script/style enqueuing with auto-versioning
 * (uses plugin version) and consistent footer loading.
 */
class Asset implements AssetInterface
{
    /**
     * Default version for cache busting.
     * Plugins should set this via Asset::setVersion().
     */
    private static string $version = '1.0.0';

    /**
     * Set the default version for all assets.
     * Typically called once during plugin bootstrap.
     */
    public static function setVersion(string $version): void
    {
        self::$version = $version;
    }

    public static function script(
        string $handle,
        string $src,
        array $deps = [],
        bool $footer = true
    ): void {
        wp_enqueue_script($handle, $src, $deps, self::$version, $footer);
    }

    public static function style(
        string $handle,
        string $src,
        array $deps = []
    ): void {
        wp_enqueue_style($handle, $src, $deps, self::$version);
    }

    public static function localize(
        string $handle,
        string $objectName,
        array $data
    ): void {
        wp_localize_script($handle, $objectName, $data);
    }
}
