<?php
/**
 * String utility helpers.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Support;

/**
 * String utility helpers.
 *
 * Pure functions — no WordPress dependency.
 */
class Str
{
    /**
     * Convert a string to snake_case.
     */
    public static function snake(string $value): string
    {
        $value = preg_replace('/[A-Z]/', '_$0', $value) ?? $value;
        return strtolower(ltrim($value, '_'));
    }

    /**
     * Convert a string to camelCase.
     */
    public static function camel(string $value): string
    {
        return lcfirst(self::studly($value));
    }

    /**
     * Convert a string to StudlyCase (PascalCase).
     */
    public static function studly(string $value): string
    {
        $words = explode(' ', str_replace(['-', '_'], ' ', $value));
        return implode('', array_map('ucfirst', $words));
    }

    /**
     * Check if a string starts with a given prefix.
     */
    public static function startsWith(string $haystack, string $needle): bool
    {
        return str_starts_with($haystack, $needle);
    }

    /**
     * Check if a string ends with a given suffix.
     */
    public static function endsWith(string $haystack, string $needle): bool
    {
        return str_ends_with($haystack, $needle);
    }

    /**
     * Check if a string contains a given substring.
     */
    public static function contains(string $haystack, string $needle): bool
    {
        return str_contains($haystack, $needle);
    }

    /**
     * Truncate a string to a given length with ellipsis.
     */
    public static function limit(string $value, int $limit = 100, string $end = '...'): string
    {
        if (mb_strlen($value) <= $limit) {
            return $value;
        }

        return mb_substr($value, 0, $limit) . $end;
    }
}
