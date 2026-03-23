<?php
/**
 * WordPress Hooks abstraction.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Contracts;

/**
 * WordPress Hooks abstraction.
 *
 * Wraps add_action, add_filter, do_action, apply_filters
 * with type-hinted, structured API.
 */
interface HooksInterface
{
    /**
     * Register an action hook.
     */
    public static function action(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void;

    /**
     * Register a filter hook.
     */
    public static function filter(string $hook, callable $callback, int $priority = 10, int $acceptedArgs = 1): void;

    /**
     * Remove an action hook.
     */
    public static function removeAction(string $hook, callable $callback, int $priority = 10): void;

    /**
     * Remove a filter hook.
     */
    public static function removeFilter(string $hook, callable $callback, int $priority = 10): void;

    /**
     * Trigger an action hook.
     */
    public static function doAction(string $hook, mixed ...$args): void;

    /**
     * Apply filters to a value.
     */
    public static function applyFilters(string $hook, mixed $value, mixed ...$args): mixed;
}
