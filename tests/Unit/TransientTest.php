<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\WordPress\Transient;

class TransientTest extends TestCase
{
    protected function setUp(): void
    {
        global $wp_transients;
        $wp_transients = [];
    }

    public function testSetAndGet(): void
    {
        Transient::set('cache_key', 'cached_value', 3600);
        $this->assertSame('cached_value', Transient::get('cache_key'));
    }

    public function testGetReturnsFalseWhenMissing(): void
    {
        $this->assertFalse(Transient::get('missing'));
    }

    public function testDelete(): void
    {
        Transient::set('delete_me', 'value');
        Transient::delete('delete_me');
        $this->assertFalse(Transient::get('delete_me'));
    }

    public function testHas(): void
    {
        Transient::set('exists', 'yes');
        $this->assertTrue(Transient::has('exists'));
        $this->assertFalse(Transient::has('nope'));
    }
}
