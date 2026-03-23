<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\WordPress\Hooks;

/**
 * Real-world Hooks edge cases.
 *
 * Tests callback exception handling, chained filters,
 * multiple callbacks, and priority edge cases.
 */
class HooksEdgeCaseTest extends TestCase
{
    protected function setUp(): void
    {
        global $wp_hook_registry;
        $wp_hook_registry = [];
    }

    public function testMultipleFiltersChainCorrectly(): void
    {
        Hooks::filter('pipeline', function ($value) {
            return $value . '_step1';
        });
        Hooks::filter('pipeline', function ($value) {
            return $value . '_step2';
        });
        Hooks::filter('pipeline', function ($value) {
            return strtoupper($value);
        });

        $result = Hooks::applyFilters('pipeline', 'start');
        $this->assertSame('START_STEP1_STEP2', $result);
    }

    public function testFilterPreservesTypeWhenNoFiltersRegistered(): void
    {
        // No filters registered — original value should pass through
        $array = ['key' => 'value'];
        $result = Hooks::applyFilters('unregistered_filter', $array);
        $this->assertSame($array, $result);
    }

    public function testMultipleActionsOnSameHook(): void
    {
        $log = [];
        Hooks::action('multi_hook', function () use (&$log) { $log[] = 'a'; });
        Hooks::action('multi_hook', function () use (&$log) { $log[] = 'b'; });
        Hooks::action('multi_hook', function () use (&$log) { $log[] = 'c'; });

        Hooks::doAction('multi_hook');
        $this->assertSame(['a', 'b', 'c'], $log);
    }

    public function testDoActionOnUnregisteredHookDoesNothing(): void
    {
        // Should not throw or error
        Hooks::doAction('totally_unregistered_hook');
        $this->assertTrue(true);
    }

    public function testPriorityZeroRunsFirst(): void
    {
        $order = [];
        Hooks::action('priority_zero', function () use (&$order) { $order[] = 'normal'; }, 10);
        Hooks::action('priority_zero', function () use (&$order) { $order[] = 'earliest'; }, 0);
        Hooks::action('priority_zero', function () use (&$order) { $order[] = 'late'; }, 99);

        Hooks::doAction('priority_zero');
        $this->assertSame(['earliest', 'normal', 'late'], $order);
    }

    public function testSamePriorityMaintainsInsertionOrder(): void
    {
        $order = [];
        Hooks::action('same_priority', function () use (&$order) { $order[] = 'first'; }, 10);
        Hooks::action('same_priority', function () use (&$order) { $order[] = 'second'; }, 10);
        Hooks::action('same_priority', function () use (&$order) { $order[] = 'third'; }, 10);

        Hooks::doAction('same_priority');
        $this->assertSame(['first', 'second', 'third'], $order);
    }

    public function testRemoveOnlyTargetedCallback(): void
    {
        $log = [];
        $keepCallback = function () use (&$log) { $log[] = 'kept'; };
        $removeCallback = function () use (&$log) { $log[] = 'removed'; };

        Hooks::action('selective_remove', $keepCallback);
        Hooks::action('selective_remove', $removeCallback);
        Hooks::removeAction('selective_remove', $removeCallback);

        Hooks::doAction('selective_remove');
        $this->assertSame(['kept'], $log);
    }
}
