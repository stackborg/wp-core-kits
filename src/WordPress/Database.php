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
     */
    public static function table(string $name): string
    {
        return self::prefix() . $name;
    }

    public static function getVar(string $query, mixed ...$args): mixed
    {
        $prepared = empty($args) ? $query : self::wpdb()->prepare($query, ...$args);
        return self::wpdb()->get_var($prepared);
    }

    public static function getResults(string $query, mixed ...$args): array
    {
        $prepared = empty($args) ? $query : self::wpdb()->prepare($query, ...$args);
        return self::wpdb()->get_results($prepared, ARRAY_A);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function getRow(string $query, mixed ...$args): object|array|null
    {
        $prepared = empty($args) ? $query : self::wpdb()->prepare($query, ...$args);
        return self::wpdb()->get_row($prepared, ARRAY_A);
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
}
