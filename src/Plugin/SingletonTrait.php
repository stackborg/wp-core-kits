<?php
/**
 * Thread-safe Singleton pattern.
 *
 * @package Stackborg\WPCoreKits
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Plugin;

/**
 * Thread-safe Singleton pattern.
 *
 * Use this trait in plugin classes that should have
 * exactly one instance throughout the application lifecycle.
 *
 * Usage:
 *   class Plugin {
 *       use SingletonTrait;
 *       protected function init(): void { ... }
 *   }
 */
trait SingletonTrait
{
    /** @var static|null The single instance. */
    private static ?self $instance = null;

    /**
     * Get the singleton instance.
     * Creates it on first call and reuses thereafter.
     */
    public static function getInstance(): static
    {
        if (static::$instance === null) {
            static::$instance = new static();

            // Call init() if the class defines it
            if (method_exists(static::$instance, 'init')) {
                static::$instance->init();
            }
        }

        return static::$instance;
    }

    /**
     * Prevent direct construction outside of getInstance().
     */
    private function __construct()
    {
        // Subclasses should use init() instead
    }

    /**
     * Prevent cloning.
     */
    private function __clone(): void
    {
    }

    /**
     * Prevent unserialization.
     */
    public function __wakeup(): void
    {
        throw new \RuntimeException('Cannot unserialize singleton');
    }

    /**
     * Reset the singleton instance (for testing only).
     */
    public static function resetInstance(): void
    {
        static::$instance = null;
    }
}
