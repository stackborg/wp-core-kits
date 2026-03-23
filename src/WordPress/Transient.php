<?php
/**
 * WordPress Transients wrapper.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\WordPress;

use Stackborg\WPCoreKits\Contracts\TransientInterface;

/**
 * WordPress Transients wrapper.
 *
 * Provides typed interface for temporary cached data.
 */
class Transient implements TransientInterface
{
    public static function get(string $key): mixed
    {
        return get_transient($key);
    }

    public static function set(string $key, mixed $value, int $ttl = 0): bool
    {
        return set_transient($key, $value, $ttl);
    }

    public static function delete(string $key): bool
    {
        return delete_transient($key);
    }

    public static function has(string $key): bool
    {
        return self::get($key) !== false;
    }
}
