<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\Plugin\HookManager;
use Stackborg\WPCoreKits\WordPress\Hooks;

class HookManagerTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset global hook registry before each test
        $GLOBALS['wp_hook_registry'] = [];
    }

    /** @test */
    public function itRegistersASingleAction(): void
    {
        $called = false;
        HookManager::register([
            ['action', 'init', function () use (&$called) {
                $called = true;
            }],
        ]);

        Hooks::doAction('init');
        $this->assertTrue($called);
    }

    /** @test */
    public function itRegistersASingleFilter(): void
    {
        HookManager::register([
            ['filter', 'the_title', function (string $title) {
                return $title . ' — modified';
            }],
        ]);

        $result = Hooks::applyFilters('the_title', 'Hello');
        $this->assertSame('Hello — modified', $result);
    }

    /** @test */
    public function itRegistersMultipleHooksAtOnce(): void
    {
        $log = [];

        HookManager::register([
            ['action', 'hook_a', function () use (&$log) {
                $log[] = 'a';
            }],
            ['action', 'hook_b', function () use (&$log) {
                $log[] = 'b';
            }],
            ['action', 'hook_c', function () use (&$log) {
                $log[] = 'c';
            }],
        ]);

        Hooks::doAction('hook_a');
        Hooks::doAction('hook_b');
        Hooks::doAction('hook_c');

        $this->assertSame(['a', 'b', 'c'], $log);
    }

    /** @test */
    public function itRespectsPriorityParameter(): void
    {
        $order = [];

        HookManager::register([
            ['action', 'priority_hook', function () use (&$order) {
                $order[] = 'late';
            }, 20],
            ['action', 'priority_hook', function () use (&$order) {
                $order[] = 'early';
            }, 5],
        ]);

        Hooks::doAction('priority_hook');
        $this->assertSame(['early', 'late'], $order);
    }

    /** @test */
    public function itRespectsAcceptedArgsParameter(): void
    {
        $received = [];

        HookManager::register([
            ['action', 'args_hook', function ($a, $b) use (&$received) {
                $received = [$a, $b];
            }, 10, 2],
        ]);

        Hooks::doAction('args_hook', 'first', 'second');
        $this->assertSame(['first', 'second'], $received);
    }

    /** @test */
    public function itHandlesMixedActionAndFilterRegistration(): void
    {
        $actionCalled = false;

        HookManager::register([
            ['action', 'mixed_action', function () use (&$actionCalled) {
                $actionCalled = true;
            }],
            ['filter', 'mixed_filter', function (string $val) {
                return strtoupper($val);
            }],
        ]);

        Hooks::doAction('mixed_action');
        $filtered = Hooks::applyFilters('mixed_filter', 'hello');

        $this->assertTrue($actionCalled);
        $this->assertSame('HELLO', $filtered);
    }

    /** @test */
    public function itHandlesEmptyArrayGracefully(): void
    {
        // Should not throw any error
        HookManager::register([]);

        $this->assertTrue(true, 'No exception thrown on empty array');
    }

    /** @test */
    public function itIgnoresUnknownHookTypes(): void
    {
        $called = false;

        // 'event' is not a valid type — should be silently ignored
        HookManager::register([
            ['event', 'unknown_hook', function () use (&$called) {
                $called = true;
            }],
        ]);

        // The hook should not have been registered
        Hooks::doAction('unknown_hook');
        $this->assertFalse($called);
    }

    /** @test */
    public function itUsesDefaultPriorityAndArgsWhenOmitted(): void
    {
        $called = false;

        HookManager::register([
            ['action', 'default_hook', function () use (&$called) {
                $called = true;
            }],
            // No priority or acceptedArgs specified — should default to 10 and 1
        ]);

        // Verify registered at priority 10
        $this->assertArrayHasKey(10, $GLOBALS['wp_hook_registry']['default_hook']);

        Hooks::doAction('default_hook');
        $this->assertTrue($called);
    }
}
