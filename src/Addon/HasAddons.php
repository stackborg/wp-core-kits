<?php
/**
 * HasAddons - opt-in trait for plugins that support addons.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Addon;

/**
 * HasAddons — opt-in trait for plugins that support addons.
 *
 * Plugins that want addon functionality simply add:
 *   use HasAddons;
 *   $this->enableAddons(__DIR__ . '/addons');
 *
 * Plugins that don't use this trait have zero addon overhead.
 */
trait HasAddons
{
    private ?AddonRegistry $addonRegistry = null;
    private ?string $addonsDir = null;

    /**
     * Enable addon support for this plugin.
     *
     * Call this in your plugin's init() method:
     *   $this->enableAddons(__DIR__ . '/addons');
     *
     * @param string $addonsDir Absolute path to addons directory
     * @param string|null $optionKey Custom wp_options key for state (auto-generated if null)
     */
    protected function enableAddons(string $addonsDir, ?string $optionKey = null): void
    {
        $this->addonsDir = $addonsDir;

        // Ensure the addons directory exists
        if (!is_dir($addonsDir)) {
            wp_mkdir_p($addonsDir);
        }

        // Auto-generate option key from plugin class name if not provided
        if ($optionKey === null) {
            $className = (new \ReflectionClass($this))->getShortName();
            $optionKey = strtolower($className) . '_addons_state';
        }

        $this->addonRegistry = new AddonRegistry($optionKey);
        $this->addonRegistry->scan($addonsDir);
        $this->addonRegistry->bootActive();
    }

    /**
     * Get the addon registry instance.
     * Returns null if addons are not enabled for this plugin.
     */
    public function getAddonRegistry(): ?AddonRegistry
    {
        return $this->addonRegistry;
    }

    /**
     * Get the addons directory path.
     */
    public function getAddonsDir(): ?string
    {
        return $this->addonsDir;
    }

    /**
     * Quick check: does this plugin have addon support enabled?
     */
    public function hasAddonSupport(): bool
    {
        return $this->addonRegistry !== null;
    }
}
