<?php

/**
 * WordPress Options wrapper with internal caching.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\WordPress;

use Stackborg\WPCoreKits\Contracts\OptionsInterface;

/**
 * WordPress Options wrapper with internal caching.
 *
 * Prevents redundant get_option() calls within the same
 * request — a common performance issue in plugins that
 * read the same option multiple times.
 */
class Options implements OptionsInterface
{
    /**
     * In-memory cache for options read during this request.
     * Prevents multiple DB hits for the same option.
     *
     * @var array<string, mixed>
     */
    private static array $cache = [];

    /**
     * Track which keys we've already loaded from DB,
     * so we can distinguish "not cached" from "cached as false".
     *
     * @var array<string, bool>
     */
    private static array $loaded = [];

    public static function get(string $key, mixed $default = false): mixed
    {
        // Return from cache if already loaded this request
        if (isset(self::$loaded[$key])) {
            // Use array_key_exists — not ?? — so cached null is honoured
            return array_key_exists($key, self::$cache) ? self::$cache[$key] : $default;
        }

        $value = get_option($key, $default);
        self::$cache[$key] = $value;
        self::$loaded[$key] = true;

        return $value;
    }

    public static function set(string $key, mixed $value): bool
    {
        $result = update_option($key, $value);

        if ($result) {
            // Update cache to keep it in sync
            self::$cache[$key] = $value;
            self::$loaded[$key] = true;
        }

        return $result;
    }

    public static function delete(string $key): bool
    {
        $result = delete_option($key);

        if ($result) {
            unset(self::$cache[$key], self::$loaded[$key]);
        }

        return $result;
    }

    public static function has(string $key): bool
    {
        // get_option returns the default when not found
        // We use a unique sentinel to detect absence
        $sentinel = '__wp_core_kits_not_found_' . uniqid();
        $value = self::get($key, $sentinel);

        return $value !== $sentinel;
    }

    /**
     * Clear the internal cache.
     * Useful in tests or when you know options have been
     * modified outside of this class.
     */
    public static function flushCache(): void
    {
        self::$cache = [];
        self::$loaded = [];
    }
}
