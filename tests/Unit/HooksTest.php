<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\WordPress\Hooks;

class HooksTest extends TestCase
{
    protected function setUp(): void
    {
        global $wp_actions, $wp_filters;
        $wp_actions = [];
        $wp_filters = [];
    }

    public function testActionRegistersCallback(): void
    {
        $called = false;
        Hooks::action('test_action', function () use (&$called) {
            $called = true;
        });
        Hooks::doAction('test_action');
        $this->assertTrue($called);
    }

    public function testActionWithPriority(): void
    {
        $order = [];
        Hooks::action('priority_test', function () use (&$order) {
            $order[] = 'second';
        }, 20);
        Hooks::action('priority_test', function () use (&$order) {
            $order[] = 'first';
        }, 10);
        Hooks::doAction('priority_test');
        $this->assertSame(['first', 'second'], $order);
    }

    public function testFilterModifiesValue(): void
    {
        Hooks::filter('test_filter', function (string $value) {
            return $value . '_modified';
        });
        $result = Hooks::applyFilters('test_filter', 'original');
        $this->assertSame('original_modified', $result);
    }

    public function testDoActionPassesArguments(): void
    {
        $received = null;
        Hooks::action('args_test', function ($arg) use (&$received) {
            $received = $arg;
        });
        Hooks::doAction('args_test', 'hello');
        $this->assertSame('hello', $received);
    }

    public function testRemoveActionStopsCallback(): void
    {
        $called = false;
        $callback = function () use (&$called) {
            $called = true;
        };
        Hooks::action('remove_test', $callback);
        Hooks::removeAction('remove_test', $callback);
        Hooks::doAction('remove_test');
        $this->assertFalse($called);
    }
}
