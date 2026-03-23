<?php

/**
 * Bulk hook registration helper.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Plugin;

use Stackborg\WPCoreKits\WordPress\Hooks;

/**
 * Bulk hook registration helper.
 *
 * Allows declaring multiple hooks in a structured way
 * instead of individual add_action/add_filter calls.
 *
 * Usage:
 *   HookManager::register([
 *       ['action', 'admin_init', [$this, 'setup']],
 *       ['action', 'rest_api_init', [$this, 'registerRoutes']],
 *       ['filter', 'plugin_action_links', [$this, 'addLinks'], 10, 2],
 *   ]);
 */
class HookManager
{
    /**
     * Register multiple hooks at once.
     *
     * Each entry: [type, hook, callback, priority?, acceptedArgs?]
     *
     * @param array<int, array{0: string, 1: string, 2: callable, 3?: int, 4?: int}> $hooks
     */
    public static function register(array $hooks): void
    {
        foreach ($hooks as $hook) {
            $type      = $hook[0]; // 'action' or 'filter'
            $hookName  = $hook[1];
            $callback  = $hook[2];
            $priority  = $hook[3] ?? 10;
            $accepted  = $hook[4] ?? 1;

            if ($type === 'action') {
                Hooks::action($hookName, $callback, $priority, $accepted);
            } elseif ($type === 'filter') {
                Hooks::filter($hookName, $callback, $priority, $accepted);
            }
        }
    }
}
