<?php

/**
 * Provider Registry - manages plugin service providers.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Plugin;

use Stackborg\WPCoreKits\Contracts\ServiceProviderInterface;

/**
 * Provider Registry — manages plugin service providers.
 *
 * Handles registration and boot ordering so providers
 * can depend on each other safely.
 *
 * Usage in Plugin class:
 *   $registry = new ProviderRegistry();
 *   $registry->add(new SettingsProvider());
 *   $registry->add(new RestApiProvider());
 *   $registry->registerAll();
 *   $registry->bootAll();
 */
class ProviderRegistry
{
    /** @var ServiceProviderInterface[] Registered providers. */
    private array $providers = [];

    /** @var bool Whether providers have been registered. */
    private bool $registered = false;

    /** @var bool Whether providers have been booted. */
    private bool $booted = false;

    /**
     * Add a provider to the registry.
     *
     * @throws \RuntimeException If providers are already registered.
     */
    public function add(ServiceProviderInterface $provider): self
    {
        if ($this->registered) {
            throw new \RuntimeException(
                'Cannot add providers after registration. Add all providers before calling registerAll().'
            );
        }

        $this->providers[] = $provider;
        return $this;
    }

    /**
     * Call register() on all providers.
     * Should be called once during plugin initialization.
     */
    public function registerAll(): void
    {
        if ($this->registered) {
            return;
        }

        foreach ($this->providers as $provider) {
            $provider->register();
        }

        $this->registered = true;
    }

    /**
     * Call boot() on all providers.
     * Should be called after registerAll().
     */
    public function bootAll(): void
    {
        if ($this->booted || !$this->registered) {
            return;
        }

        foreach ($this->providers as $provider) {
            $provider->boot();
        }

        $this->booted = true;
    }

    /**
     * Get all registered providers.
     *
     * @return ServiceProviderInterface[]
     */
    public function all(): array
    {
        return $this->providers;
    }

    /**
     * Get count of registered providers.
     */
    public function count(): int
    {
        return count($this->providers);
    }
}
