<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\Plugin\SingletonTrait;
use Stackborg\WPCoreKits\Plugin\ServiceProvider;
use Stackborg\WPCoreKits\Plugin\ProviderRegistry;
use Stackborg\WPCoreKits\Plugin\HookManager;
use Stackborg\WPCoreKits\WordPress\Hooks;

class CountingProvider extends ServiceProvider
{
    public static int $registerCount = 0;
    public static int $bootCount = 0;

    public function register(): void
    {
        self::$registerCount++;
    }

    public function boot(): void
    {
        self::$bootCount++;
    }
}

/**
 * Plugin class using SingletonTrait for testing.
 */
class TestPlugin
{
    use SingletonTrait;

    public ProviderRegistry $providers;
    public bool $initialized = false;

    protected function init(): void
    {
        $this->initialized = true;
        $this->providers = new ProviderRegistry();
        $this->providers->add(new CountingProvider());
        $this->providers->registerAll();
        $this->providers->bootAll();
    }
}

/**
 * Feature test — Full plugin lifecycle:
 * Singleton → ProviderRegistry → ServiceProvider → HookManager
 */
class PluginLifecycleTest extends TestCase
{
    protected function setUp(): void
    {
        TestPlugin::resetInstance();
        CountingProvider::$registerCount = 0;
        CountingProvider::$bootCount = 0;
        global $wp_hook_registry;
        $wp_hook_registry = [];
    }

    public function testSingletonInitializesOnce(): void
    {
        $first = TestPlugin::getInstance();
        $second = TestPlugin::getInstance();

        $this->assertSame($first, $second);
        $this->assertTrue($first->initialized);
    }

    public function testProviderRegisterAndBootCalled(): void
    {
        TestPlugin::getInstance();

        $this->assertSame(1, CountingProvider::$registerCount);
        $this->assertSame(1, CountingProvider::$bootCount);
    }

    public function testHookManagerBulkRegistration(): void
    {
        $results = [];

        HookManager::register([
            ['action', 'init', function () use (&$results) { $results[] = 'init'; }],
            ['action', 'admin_init', function () use (&$results) { $results[] = 'admin_init'; }],
            ['filter', 'the_title', function ($title) { return strtoupper($title); }],
        ]);

        Hooks::doAction('init');
        Hooks::doAction('admin_init');
        $title = Hooks::applyFilters('the_title', 'hello');

        $this->assertSame(['init', 'admin_init'], $results);
        $this->assertSame('HELLO', $title);
    }

    public function testResetInstanceAllowsReinitialization(): void
    {
        $first = TestPlugin::getInstance();
        TestPlugin::resetInstance();
        CountingProvider::$registerCount = 0;

        $second = TestPlugin::getInstance();
        $this->assertNotSame($first, $second);
        $this->assertSame(1, CountingProvider::$registerCount);
    }
}
