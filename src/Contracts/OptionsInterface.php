<?php

/**
 * WordPress Options abstraction.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Contracts;

/**
 * WordPress Options abstraction.
 *
 * Provides a clean, typed interface to WordPress options API.
 * Implementations may add caching, validation, or serialization
 * without plugins needing to change their code.
 */
interface OptionsInterface
{
    /**
     * Retrieve an option value.
     *
     * @param string $key     Option name.
     * @param mixed  $default Default value if option doesn't exist.
     * @return mixed
     */
    public static function get(string $key, mixed $default = false): mixed;

    /**
     * Update or create an option.
     *
     * @param string $key   Option name.
     * @param mixed  $value Option value.
     * @return bool True on success.
     */
    public static function set(string $key, mixed $value): bool;

    /**
     * Delete an option.
     *
     * @param string $key Option name.
     * @return bool True on success.
     */
    public static function delete(string $key): bool;

    /**
     * Check if an option exists.
     *
     * @param string $key Option name.
     * @return bool
     */
    public static function has(string $key): bool;
}
