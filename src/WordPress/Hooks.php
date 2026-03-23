<?php
/**
 * WordPress Hooks wrapper.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\WordPress;

use Stackborg\WPCoreKits\Contracts\HooksInterface;

/**
 * WordPress Hooks wrapper.
 *
 * Provides clean, typed access to WP hook system.
 * All hook registrations flow through here so they can
 * be tracked, debugged, and tested consistently.
 */
class Hooks implements HooksInterface
{
    public static function action(
        string $hook,
        callable $callback,
        int $priority = 10,
        int $acceptedArgs = 1
    ): void {
        add_action($hook, $callback, $priority, $acceptedArgs);
    }

    public static function filter(
        string $hook,
        callable $callback,
        int $priority = 10,
        int $acceptedArgs = 1
    ): void {
        add_filter($hook, $callback, $priority, $acceptedArgs);
    }

    public static function removeAction(
        string $hook,
        callable $callback,
        int $priority = 10
    ): void {
        remove_action($hook, $callback, $priority);
    }

    public static function removeFilter(
        string $hook,
        callable $callback,
        int $priority = 10
    ): void {
        remove_filter($hook, $callback, $priority);
    }

    public static function doAction(string $hook, mixed ...$args): void
    {
        do_action($hook, ...$args);
    }

    public static function applyFilters(string $hook, mixed $value, mixed ...$args): mixed
    {
        return apply_filters($hook, $value, ...$args);
    }
}
