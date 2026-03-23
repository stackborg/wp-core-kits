<?php
/**
 * Asset management abstraction.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Contracts;

/**
 * Asset management abstraction.
 *
 * Simplifies wp_enqueue_script/style with auto-versioning,
 * conditional loading, and localization support.
 */
interface AssetInterface
{
    /**
     * Enqueue a JavaScript file.
     *
     * @param string   $handle Unique handle.
     * @param string   $src    Source URL.
     * @param string[] $deps   Dependencies.
     * @param bool     $footer Load in footer.
     */
    public static function script(
        string $handle,
        string $src,
        array $deps = [],
        bool $footer = true
    ): void;

    /**
     * Enqueue a CSS stylesheet.
     *
     * @param string   $handle Unique handle.
     * @param string   $src    Source URL.
     * @param string[] $deps   Dependencies.
     */
    public static function style(
        string $handle,
        string $src,
        array $deps = []
    ): void;

    /**
     * Pass data from PHP to a JavaScript file.
     *
     * @param string $handle     Script handle.
     * @param string $objectName JS global variable name.
     * @param array<string, mixed> $data Data to localize.
     */
    public static function localize(
        string $handle,
        string $objectName,
        array $data
    ): void;
}
