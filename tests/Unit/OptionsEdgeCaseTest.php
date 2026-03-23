<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\WordPress\Options;

/**
 * Real-world Options edge cases.
 *
 * Tests serialized data, type preservation, boolean edge
 * cases, and concurrent access patterns.
 */
class OptionsEdgeCaseTest extends TestCase
{
    protected function setUp(): void
    {
        Options::flushCache();
        global $wp_options;
        $wp_options = [];
    }

    public function testPreservesNestedArray(): void
    {
        $settings = [
            'general' => ['site_name' => 'Test', 'debug' => true],
            'api' => ['key' => 'abc123', 'timeout' => 30],
            'features' => ['billing', 'reports', 'export'],
        ];
        Options::set('complex_settings', $settings);
        $retrieved = Options::get('complex_settings');

        $this->assertSame($settings, $retrieved);
        $this->assertTrue($retrieved['general']['debug']);
        $this->assertSame(30, $retrieved['api']['timeout']);
    }

    public function testPreservesIntegerTypes(): void
    {
        Options::set('count', 42);
        $this->assertSame(42, Options::get('count'));
        $this->assertIsInt(Options::get('count'));
    }

    public function testPreservesBooleanFalse(): void
    {
        // false vs "not exists" is a real WP trap
        Options::set('disabled', false);
        $this->assertFalse(Options::get('disabled'));
    }

    public function testDistinguishesFalseFromNotExists(): void
    {
        // A option set to false should NOT return default
        Options::set('is_active', false);
        $result = Options::get('is_active', 'default_value');
        $this->assertFalse($result);
        $this->assertNotSame('default_value', $result);
    }

    public function testPreservesNull(): void
    {
        Options::set('nullable', null);
        $this->assertNull(Options::get('nullable'));
    }

    public function testEmptyStringIsNotDefault(): void
    {
        Options::set('empty', '');
        $this->assertSame('', Options::get('empty', 'fallback'));
    }

    public function testHandlesSpecialCharactersInKey(): void
    {
        Options::set('my_plugin:api_key', 'secret');
        $this->assertSame('secret', Options::get('my_plugin:api_key'));
    }

    public function testOverwriteExistingValue(): void
    {
        Options::set('key', 'v1');
        Options::set('key', 'v2');
        $this->assertSame('v2', Options::get('key'));
    }

    public function testDeleteNonExistentKeyDoesNotError(): void
    {
        // Should not throw
        $result = Options::delete('never_existed_' . uniqid());
        $this->assertTrue($result);
    }

    public function testCacheUpdatesAfterSet(): void
    {
        Options::set('cached', 'first');
        Options::get('cached'); // prime cache
        Options::set('cached', 'second');
        // Cache should reflect new value without flushCache()
        $this->assertSame('second', Options::get('cached'));
    }

    public function testCacheUpdatesAfterDelete(): void
    {
        Options::set('temp', 'value');
        Options::get('temp'); // prime cache
        Options::delete('temp');
        // Should return default after delete, not cached value
        $this->assertSame('gone', Options::get('temp', 'gone'));
    }

    public function testLargeDataStorage(): void
    {
        $large = str_repeat('x', 10000);
        Options::set('big_data', $large);
        $this->assertSame(10000, strlen(Options::get('big_data')));
    }
}
