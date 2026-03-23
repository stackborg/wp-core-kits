<?php

/**
 * Service Provider contract for plugin modules.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Contracts;

/**
 * Service Provider contract for plugin modules.
 *
 * Each plugin feature area (settings, REST API, etc.)
 * should be encapsulated in a ServiceProvider that
 * registers its hooks and services.
 */
interface ServiceProviderInterface
{
    /**
     * Register services, hooks, and routes.
     * Called during plugin initialization.
     */
    public function register(): void;

    /**
     * Boot the provider after all providers are registered.
     * Use this for logic that depends on other providers.
     */
    public function boot(): void;
}
