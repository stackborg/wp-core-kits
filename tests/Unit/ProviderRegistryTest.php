<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\Plugin\ProviderRegistry;
use Stackborg\WPCoreKits\Plugin\ServiceProvider;

class TestProvider extends ServiceProvider
{
    public bool $registered = false;
    public bool $booted = false;

    public function register(): void
    {
        $this->registered = true;
    }

    public function boot(): void
    {
        $this->booted = true;
    }
}

class ProviderRegistryTest extends TestCase
{
    public function testAddAndCount(): void
    {
        $registry = new ProviderRegistry();
        $registry->add(new TestProvider());
        $registry->add(new TestProvider());

        $this->assertSame(2, $registry->count());
    }

    public function testRegisterAllCallsRegisterOnProviders(): void
    {
        $provider = new TestProvider();
        $registry = new ProviderRegistry();
        $registry->add($provider);
        $registry->registerAll();

        $this->assertTrue($provider->registered);
        $this->assertFalse($provider->booted);
    }

    public function testBootAllCallsBootOnProviders(): void
    {
        $provider = new TestProvider();
        $registry = new ProviderRegistry();
        $registry->add($provider);
        $registry->registerAll();
        $registry->bootAll();

        $this->assertTrue($provider->booted);
    }

    public function testBootAllWithoutRegisterDoesNothing(): void
    {
        $provider = new TestProvider();
        $registry = new ProviderRegistry();
        $registry->add($provider);
        $registry->bootAll(); // no registerAll() first

        $this->assertFalse($provider->booted);
    }

    public function testCannotAddAfterRegistration(): void
    {
        $registry = new ProviderRegistry();
        $registry->add(new TestProvider());
        $registry->registerAll();

        $this->expectException(\RuntimeException::class);
        $registry->add(new TestProvider());
    }

    public function testRegisterAllIsIdempotent(): void
    {
        $provider = new TestProvider();
        $registry = new ProviderRegistry();
        $registry->add($provider);
        $registry->registerAll();
        $provider->registered = false; // reset flag
        $registry->registerAll(); // should not call again

        $this->assertFalse($provider->registered);
    }

    public function testAllReturnsProviders(): void
    {
        $registry = new ProviderRegistry();
        $p1 = new TestProvider();
        $p2 = new TestProvider();
        $registry->add($p1);
        $registry->add($p2);

        $this->assertSame([$p1, $p2], $registry->all());
    }
}
