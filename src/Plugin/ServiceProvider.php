<?php
/**
 * Base Service Provider.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Plugin;

use Stackborg\WPCoreKits\Contracts\ServiceProviderInterface;

/**
 * Base Service Provider.
 *
 * Provides a default boot() implementation so simple
 * providers only need to implement register().
 */
abstract class ServiceProvider implements ServiceProviderInterface
{
    /**
     * Boot the provider.
     * Override in subclass when boot logic is needed.
     */
    public function boot(): void
    {
        // Default: no-op. Override if needed.
    }
}
