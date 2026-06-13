<?php

/**
 * Typed $wpdb wrapper with auto table prefix.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\WordPress;

use Stackborg\WPCoreKits\Contracts\DatabaseInterface;

/**
 * Typed $wpdb wrapper with auto table prefix.
 *
 * All queries use prepared statements by default,
 * preventing SQL injection. Table names are auto-prefixed.
 *
 * Methods like insert/update/delete accept a short table name
 * (e.g. 'my_table') and auto-prefix it, or a full table name
 * (already containing the prefix) which is detected and used as-is.
 */
class Database implements DatabaseInterface
{
    /**
     * Get the global $wpdb instance.
     */
    private static function wpdb(): object
    {
        return $GLOBALS['wpdb'];
    }

    public static function prefix(): string
    {
        return self::wpdb()->prefix;
    }

    /**
     * Build full table name with prefix.
     *
     * If the table name already starts with the prefix, return as-is
     * to avoid double-prefixing.
     */
    public static function table(string $name): string
    {
        $prefix = self::prefix();
        // Avoid double-prefix when callers pass the full table name
        if (str_starts_with($name, $prefix)) {
            return $name;
        }
        return $prefix . $name;
    }

    public static function getVar(string $query, mixed ...$args): mixed
    {
        $prepared = empty($args) ? $query : self::wpdb()->prepare($query, ...$args);
        return self::wpdb()->get_var($prepared);
    }

    /**
     * Get multiple rows as associative arrays by default.
     *
     * @param string $query   SQL query string.
     * @param mixed  ...$args Variadic args. If the first arg is ARRAY_A, ARRAY_N,
     *                        or OBJECT, it is treated as the output type; remaining
     *                        args are used for prepare(). If omitted, defaults to ARRAY_A.
     * @return array
     */
    public static function getResults(string $query, mixed ...$args): array
    {
        $outputType = ARRAY_A;
        $prepArgs = $args;

        // Check if first arg is an output type constant
        if (!empty($args) && is_string($args[0]) && in_array($args[0], [ARRAY_A, ARRAY_N, OBJECT], true)) {
            $outputType = $args[0];
            $prepArgs = array_slice($args, 1);
        }

        $prepared = empty($prepArgs) ? $query : self::wpdb()->prepare($query, ...$prepArgs);
        return self::wpdb()->get_results($prepared, $outputType) ?: [];
    }

    /**
     * Get a single row as an associative array by default.
     *
     * @param string $query   SQL query string.
     * @param mixed  ...$args Variadic args. If the first arg is ARRAY_A, ARRAY_N,
     *                        or OBJECT, it is treated as the output type; remaining
     *                        args are used for prepare(). If omitted, defaults to ARRAY_A.
     * @return array<string, mixed>|null
     */
    public static function getRow(string $query, mixed ...$args): ?array
    {
        $outputType = ARRAY_A;
        $prepArgs = $args;

        // Check if first arg is an output type constant
        if (!empty($args) && is_string($args[0]) && in_array($args[0], [ARRAY_A, ARRAY_N, OBJECT], true)) {
            $outputType = $args[0];
            $prepArgs = array_slice($args, 1);
        }

        $prepared = empty($prepArgs) ? $query : self::wpdb()->prepare($query, ...$prepArgs);
        return self::wpdb()->get_row($prepared, $outputType);
    }

    public static function insert(string $table, array $data): int|false
    {
        $result = self::wpdb()->insert(self::table($table), $data);
        return $result ? self::wpdb()->insert_id : false;
    }

    public static function update(string $table, array $data, array $where): int|false
    {
        return self::wpdb()->update(self::table($table), $data, $where);
    }

    public static function delete(string $table, array $where): int|false
    {
        return self::wpdb()->delete(self::table($table), $where);
    }

    public static function query(string $query, mixed ...$args): int|bool
    {
        $prepared = empty($args) ? $query : self::wpdb()->prepare($query, ...$args);
        return self::wpdb()->query($prepared);
    }

    // ── Extended methods for complex query patterns ──────────

    /**
     * Prepare a SQL query for safe execution.
     *
     * Uses WordPress $wpdb->prepare() internally.
     */
    public static function prepare(string $query, mixed ...$args): ?string
    {
        if (empty($args)) {
            return $query;
        }
        return self::wpdb()->prepare($query, ...$args);
    }

    /**
     * Get the auto-increment ID from the last INSERT operation.
     */
    public static function insertId(): int
    {
        return (int) self::wpdb()->insert_id;
    }

    /**
     * Get a single column from query results.
     *
     * @return array<int, string>
     */
    public static function getCol(string $query, mixed ...$args): array
    {
        $prepared = empty($args) ? $query : self::wpdb()->prepare($query, ...$args);
        return self::wpdb()->get_col($prepared) ?: [];
    }

    /**
     * Escape a string for use in a LIKE clause.
     *
     * Escapes %, _ and \ characters to be used literally in LIKE patterns.
     */
    public static function escLike(string $text): string
    {
        return self::wpdb()->esc_like($text);
    }

    /**
     * Get the charset collation string for CREATE TABLE statements.
     *
     * Returns the $wpdb->get_charset_collate() string,
     * e.g. "DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci".
     */
    public static function charsetCollate(): string
    {
        return self::wpdb()->get_charset_collate();
    }
}
