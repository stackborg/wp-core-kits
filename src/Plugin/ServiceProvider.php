<?php

/**
 * Base Service Provider with optional settings injection.
 *
 * Provides settings-aware functionality so providers can check
 * feature toggles and access plugin settings without direct
 * coupling to the Plugin class.
 *
 * @package Stackborg\WPCoreKits
 * @since   1.0.0
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Plugin;

use Stackborg\WPCoreKits\Contracts\ServiceProviderInterface;

/**
 * Base Service Provider with settings injection.
 *
 * Subclasses can optionally receive settings in the constructor
 * and use isEnabled()/getSetting() helpers for feature gating.
 *
 * Usage:
 *   class CacheServiceProvider extends ServiceProvider {
 *       public function register(): void {
 *           if ($this->isEnabled('page_cache')) {
 *               // Register page cache hooks
 *           }
 *       }
 *   }
 */
abstract class ServiceProvider implements ServiceProviderInterface
{
    /** @var array<string, mixed> Plugin settings for feature gating. */
    protected array $settings = [];

    /**
     * Create a new service provider instance.
     *
     * @param array<string, mixed> $settings  Optional plugin settings for feature checks.
     */
    public function __construct(array $settings = [])
    {
        $this->settings = $settings;
    }

    /**
     * Check if a specific setting/feature is enabled (truthy).
     *
     * @param string $key  Setting key to check.
     * @return bool        True if the setting exists and is truthy.
     */
    protected function isEnabled(string $key): bool
    {
        return !empty($this->settings[$key]);
    }

    /**
     * Get a setting value with a fallback default.
     *
     * @param string $key      Setting key to retrieve.
     * @param mixed  $default  Default value if key doesn't exist.
     * @return mixed           The setting value or default.
     */
    protected function getSetting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /**
     * Boot the provider.
     * Override in subclass when post-registration logic is needed.
     */
    public function boot(): void
    {
        // Default: no-op. Override if needed.
    }
}
