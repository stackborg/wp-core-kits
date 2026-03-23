<?php
/**
 * Transient (cached data) abstraction.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Contracts;

/**
 * Transient (cached data) abstraction.
 *
 * Provides typed interface for WP transients —
 * with auto-serialization for complex data.
 */
interface TransientInterface
{
    /** Get a transient value. Returns false if expired or missing. */
    public static function get(string $key): mixed;

    /** Set a transient with optional TTL in seconds. */
    public static function set(string $key, mixed $value, int $ttl = 0): bool;

    /** Delete a transient. */
    public static function delete(string $key): bool;

    /** Check if a transient exists and is not expired. */
    public static function has(string $key): bool;
}
