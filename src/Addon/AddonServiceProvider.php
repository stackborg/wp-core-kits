<?php

/**
 * AddonServiceProvider - base class for addon feature providers.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Addon;

use Stackborg\WPCoreKits\Plugin\ServiceProvider;

/**
 * AddonServiceProvider — base class for addon feature providers.
 *
 * Extends the core ServiceProvider with addon-specific context,
 * so each provider knows which addon it belongs to.
 *
 * Usage in an addon:
 *   class SequenceProvider extends AddonServiceProvider {
 *       public function register(): void { ... }
 *       public function boot(): void { ... }
 *   }
 */
abstract class AddonServiceProvider extends ServiceProvider
{
    /**
     * Addon slug this provider belongs to.
     * Set by AddonRegistry during boot.
     */
    private string $addonSlug = '';

    /**
     * Set the addon context. Called by AddonRegistry, not by users.
     *
     * @internal
     */
    public function setAddonSlug(string $slug): void
    {
        $this->addonSlug = $slug;
    }

    /**
     * Get the addon slug this provider belongs to.
     * Useful for scoping options, hooks, and routes to the addon.
     */
    protected function addonSlug(): string
    {
        return $this->addonSlug;
    }
}
