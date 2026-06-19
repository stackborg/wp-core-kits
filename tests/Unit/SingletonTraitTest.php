<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\Plugin\SingletonTrait;

/**
 * Concrete class using SingletonTrait without init().
 */
class StubSingletonBasic
{
    use SingletonTrait;
}

/**
 * Concrete class using SingletonTrait with init().
 */
class StubSingletonWithInit
{
    use SingletonTrait;

    public bool $initialized = false;

    protected function init(): void
    {
        $this->initialized = true;
    }
}

/**
 * Second concrete class to verify independent instances.
 */
class StubSingletonOther
{
    use SingletonTrait;
}

class SingletonTraitTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset all singleton instances before each test
        StubSingletonBasic::resetInstance();
        StubSingletonWithInit::resetInstance();
        StubSingletonOther::resetInstance();
    }

    /** @test */
    public function itReturnsSameInstanceOnMultipleCalls(): void
    {
        $first  = StubSingletonBasic::getInstance();
        $second = StubSingletonBasic::getInstance();

        $this->assertSame($first, $second);
    }

    /** @test */
    public function itReturnsNewInstanceAfterReset(): void
    {
        $first = StubSingletonBasic::getInstance();
        StubSingletonBasic::resetInstance();
        $second = StubSingletonBasic::getInstance();

        $this->assertNotSame($first, $second);
    }

    /** @test */
    public function itCallsInitMethodOnFirstCreation(): void
    {
        $instance = StubSingletonWithInit::getInstance();

        $this->assertTrue($instance->initialized);
    }

    /** @test */
    public function itDoesNotCallInitOnSubsequentGetInstance(): void
    {
        $instance = StubSingletonWithInit::getInstance();
        // Manually set to false to detect if init() is called again
        $instance->initialized = false;

        $same = StubSingletonWithInit::getInstance();

        $this->assertFalse($same->initialized, 'init() should NOT be called again');
        $this->assertSame($instance, $same);
    }

    /** @test */
    public function itMaintainsIndependentInstancesPerClass(): void
    {
        $basic = StubSingletonBasic::getInstance();
        $other = StubSingletonOther::getInstance();

        $this->assertNotSame($basic, $other);
        $this->assertInstanceOf(StubSingletonBasic::class, $basic);
        $this->assertInstanceOf(StubSingletonOther::class, $other);
    }

    /** @test */
    public function itThrowsOnWakeup(): void
    {
        $instance = StubSingletonBasic::getInstance();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot unserialize singleton');

        $instance->__wakeup();
    }

    /** @test */
    public function itResetOnlyClearsTargetClass(): void
    {
        $basic = StubSingletonBasic::getInstance();
        $other = StubSingletonOther::getInstance();

        StubSingletonBasic::resetInstance();

        // Basic should return a new instance
        $newBasic = StubSingletonBasic::getInstance();
        $this->assertNotSame($basic, $newBasic);

        // Other should still be the same
        $sameOther = StubSingletonOther::getInstance();
        $this->assertSame($other, $sameOther);
    }

    /** @test */
    public function itReturnsCorrectTypeFromGetInstance(): void
    {
        $instance = StubSingletonWithInit::getInstance();
        $this->assertInstanceOf(StubSingletonWithInit::class, $instance);
    }
}
