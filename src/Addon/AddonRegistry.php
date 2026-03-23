<?php

/**
 * AddonRegistry - discovers, tracks, and manages addon lifecycle.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Addon;

use Stackborg\WPCoreKits\Contracts\AddonInterface;

/**
 * AddonRegistry — discovers, tracks, and manages addon lifecycle.
 *
 * Scans the addons directory, registers discovered addons,
 * and manages their active/inactive state via wp_options.
 *
 * State is persisted in: {prefix}_addons_state option.
 */
class AddonRegistry
{
    /** @var array<string, AddonInterface> slug => addon instance */
    private array $addons = [];

    /** @var array<string, array{active: bool, installed_at: string, version: string}> */
    private array $state = [];

    private bool $booted = false;

    /**
     * @param string $optionKey wp_options key for state persistence
     */
    public function __construct(
        private readonly string $optionKey
    ) {
        $this->loadState();
    }

    /**
     * Scan addons directory and register all discovered addons.
     * Each addon directory must contain an addon.json and a PHP
     * class implementing AddonInterface.
     */
    public function scan(string $addonsDir): void
    {
        if (!is_dir($addonsDir)) {
            return;
        }

        $dirs = glob(rtrim($addonsDir, '/') . '/*', GLOB_ONLYDIR);
        if ($dirs === false) {
            return;
        }

        foreach ($dirs as $dir) {
            $addonJsonPath = $dir . '/addon.json';
            if (!file_exists($addonJsonPath)) {
                continue;
            }

            try {
                $meta = AddonMeta::fromPath($dir);

                // Look for the main addon class file
                // Convention: addon.json has a "main_class" field,
                // or we autoload via the addon's providers
                $slug = $meta->slug;

                // Check if it's already registered (e.g. manually)
                if (isset($this->addons[$slug])) {
                    continue;
                }

                // Ensure state entry exists for installed addon
                if (!isset($this->state[$slug])) {
                    $this->state[$slug] = [
                        'active'       => false,
                        'installed_at' => gmdate('Y-m-d\TH:i:s\Z'),
                        'version'      => $meta->version,
                    ];
                    $this->saveState();
                }
            } catch (\RuntimeException $e) {
                // Skip malformed addons silently — log in production
                continue;
            }
        }
    }

    /**
     * Register an addon instance.
     * Called by the addon itself or by scan() after discovering.
     */
    public function register(AddonInterface $addon): void
    {
        $slug = $addon->slug();
        $this->addons[$slug] = $addon;

        // Ensure state entry exists
        if (!isset($this->state[$slug])) {
            $this->state[$slug] = [
                'active'       => false,
                'installed_at' => gmdate('Y-m-d\TH:i:s\Z'),
                'version'      => $addon->version(),
            ];
            $this->saveState();
        }
    }

    /**
     * Activate an installed addon.
     */
    public function activate(string $slug): bool
    {
        if (!$this->isInstalled($slug)) {
            return false;
        }

        if ($this->isActive($slug)) {
            return true; // already active
        }

        $this->state[$slug]['active'] = true;
        $this->saveState();

        return true;
    }

    /**
     * Deactivate an active addon.
     * Does not remove files — addon stays installed but inactive.
     */
    public function deactivate(string $slug): bool
    {
        if (!$this->isInstalled($slug)) {
            return false;
        }

        $this->state[$slug]['active'] = false;
        $this->saveState();

        return true;
    }

    /**
     * Remove an addon from the registry (called after files are deleted).
     */
    public function remove(string $slug): void
    {
        unset($this->addons[$slug], $this->state[$slug]);
        $this->saveState();
    }

    /**
     * Check if an addon is installed (has state entry).
     */
    public function isInstalled(string $slug): bool
    {
        return isset($this->state[$slug]);
    }

    /**
     * Check if an addon is currently active.
     */
    public function isActive(string $slug): bool
    {
        return ($this->state[$slug]['active'] ?? false) === true;
    }

    /**
     * Get addon instance by slug. Returns null if not registered.
     */
    public function get(string $slug): ?AddonInterface
    {
        return $this->addons[$slug] ?? null;
    }

    /**
     * Get all registered addons.
     *
     * @return array<string, AddonInterface>
     */
    public function getAll(): array
    {
        return $this->addons;
    }

    /**
     * Get only active addons.
     *
     * @return array<string, AddonInterface>
     */
    public function getActive(): array
    {
        return array_filter(
            $this->addons,
            fn(AddonInterface $addon) => $this->isActive($addon->slug())
        );
    }

    /**
     * Get full state for all addons (for REST API responses).
     *
     * @return array<string, array{active: bool, installed_at: string, version: string}>
     */
    public function getState(): array
    {
        return $this->state;
    }

    /**
     * Get installed addon count.
     */
    public function count(): int
    {
        return count($this->state);
    }

    /**
     * Boot all active addons — calls register() then boot()
     * on each addon's service providers.
     *
     * Should be called once during plugin initialization.
     */
    public function bootActive(): void
    {
        if ($this->booted) {
            return; // prevent double-boot
        }

        foreach ($this->getActive() as $addon) {
            foreach ($addon->providers() as $providerClass) {
                if (!class_exists($providerClass)) {
                    continue;
                }

                /** @var \Stackborg\WPCoreKits\Plugin\ServiceProvider $provider */
                $provider = new $providerClass();

                // Set addon context if it's an AddonServiceProvider
                if ($provider instanceof AddonServiceProvider) {
                    $provider->setAddonSlug($addon->slug());
                }

                $provider->register();
                $provider->boot();
            }
        }

        $this->booted = true;
    }

    /**
     * Load persisted state from wp_options.
     */
    private function loadState(): void
    {
        $stored = get_option($this->optionKey, []);
        $this->state = is_array($stored) ? $stored : [];
    }

    /**
     * Save state to wp_options.
     */
    private function saveState(): void
    {
        update_option($this->optionKey, $this->state);
    }
}
