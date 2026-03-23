<?php

/**
 * Array utility helpers.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Support;

/**
 * Array utility helpers.
 *
 * Pure functions — no WordPress dependency.
 */
class Arr
{
    /**
     * Get a value from a nested array using dot notation.
     *
     * Arr::get($config, 'database.host', 'localhost')
     *
     * @param array<string, mixed> $array
     */
    public static function get(array $array, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $array)) {
            return $array[$key];
        }

        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }
            $array = $array[$segment];
        }

        return $array;
    }

    /**
     * Set a value in a nested array using dot notation.
     *
     * Arr::set($config, 'database.host', '127.0.0.1')
     *
     * @param array<string, mixed> $array
     */
    public static function set(array &$array, string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $current = &$array;

        foreach ($keys as $i => $segment) {
            if ($i === count($keys) - 1) {
                $current[$segment] = $value;
            } else {
                if (!isset($current[$segment]) || !is_array($current[$segment])) {
                    $current[$segment] = [];
                }
                $current = &$current[$segment];
            }
        }
    }

    /**
     * Check if a key exists in a nested array using dot notation.
     *
     * @param array<string, mixed> $array
     */
    public static function has(array $array, string $key): bool
    {
        $sentinel = '__arr_not_found_' . uniqid();
        return self::get($array, $key, $sentinel) !== $sentinel;
    }

    /**
     * Return only the specified keys from an array.
     *
     * @param array<string, mixed> $array
     * @param string[] $keys
     * @return array<string, mixed>
     */
    public static function only(array $array, array $keys): array
    {
        return array_intersect_key($array, array_flip($keys));
    }

    /**
     * Return all keys except the specified ones.
     *
     * @param array<string, mixed> $array
     * @param string[] $keys
     * @return array<string, mixed>
     */
    public static function except(array $array, array $keys): array
    {
        return array_diff_key($array, array_flip($keys));
    }
}
