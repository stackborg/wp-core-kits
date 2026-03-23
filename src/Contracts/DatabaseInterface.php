<?php
/**
 * Database abstraction.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Contracts;

/**
 * Database abstraction.
 *
 * Typed wrapper around $wpdb with prepared statements
 * and safe query building.
 */
interface DatabaseInterface
{
    /** Get the table prefix (e.g. 'wp_'). */
    public static function prefix(): string;

    /** Get a single variable from the database. */
    public static function getVar(string $query, mixed ...$args): mixed;

    /**
     * Get multiple rows from the database.
     *
     * @return array<int, object|array<string, mixed>>
     */
    public static function getResults(string $query, mixed ...$args): array;

    /**
     * Get a single row from the database.
     *
     * @return array<string, mixed>|null
     */
    public static function getRow(string $query, mixed ...$args): object|array|null;

    /**
     * Insert a row into a table.
     *
     * @param string                $table Table name (without prefix).
     * @param array<string, mixed>  $data  Column => value pairs.
     * @return int|false Insert ID on success, false on failure.
     */
    public static function insert(string $table, array $data): int|false;

    /**
     * Update rows in a table.
     *
     * @param string                $table Table name (without prefix).
     * @param array<string, mixed>  $data  Column => value pairs to update.
     * @param array<string, mixed>  $where WHERE conditions.
     * @return int|false Number of rows updated, false on failure.
     */
    public static function update(string $table, array $data, array $where): int|false;

    /**
     * Delete rows from a table.
     *
     * @param string                $table Table name (without prefix).
     * @param array<string, mixed>  $where WHERE conditions.
     * @return int|false Number of rows deleted, false on failure.
     */
    public static function delete(string $table, array $where): int|false;

    /** Execute a raw query. */
    public static function query(string $query, mixed ...$args): int|bool;
}
