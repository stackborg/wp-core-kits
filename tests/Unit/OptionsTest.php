<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\WordPress\Options;

class OptionsTest extends TestCase
{
    protected function setUp(): void
    {
        Options::flushCache();
        // Reset mock storage
        global $wp_options;
        $wp_options = [];
    }

    public function testGetReturnsDefaultWhenKeyNotExists(): void
    {
        $result = Options::get('nonexistent', 'fallback');
        $this->assertSame('fallback', $result);
    }

    public function testSetAndGet(): void
    {
        Options::set('test_key', 'hello');
        $this->assertSame('hello', Options::get('test_key'));
    }

    public function testSetReturnsBool(): void
    {
        $result = Options::set('key', 'value');
        $this->assertTrue($result);
    }

    public function testDeleteRemovesOption(): void
    {
        Options::set('delete_me', 'yes');
        $this->assertTrue(Options::delete('delete_me'));
    }

    public function testHasReturnsTrueForExistingOption(): void
    {
        Options::set('exists', 'yep');
        $this->assertTrue(Options::has('exists'));
    }

    public function testHasReturnsFalseForMissingOption(): void
    {
        $this->assertFalse(Options::has('nope_' . uniqid()));
    }

    public function testCachePreventsDuplicateReads(): void
    {
        global $wp_options_read_count;
        $wp_options_read_count = 0;

        Options::set('cached_key', 'value');
        Options::flushCache();

        // First read — hits DB
        Options::get('cached_key');
        $firstCount = $wp_options_read_count;

        // Second read — should use cache
        Options::get('cached_key');
        $this->assertSame($firstCount, $wp_options_read_count);
    }

    public function testFlushCacheClearsAllCachedValues(): void
    {
        Options::set('flush_test', 'value');
        Options::get('flush_test'); // cache it
        Options::flushCache();

        // After flush, should read from DB again
        global $wp_options_read_count;
        $before = $wp_options_read_count;
        Options::get('flush_test');
        $this->assertGreaterThan($before, $wp_options_read_count);
    }
}
