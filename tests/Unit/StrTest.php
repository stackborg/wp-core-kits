<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\Support\Str;

class StrTest extends TestCase
{
    public function testSnake(): void
    {
        $this->assertSame('my_plugin', Str::snake('myPlugin'));
        $this->assertSame('my_long_name', Str::snake('MyLongName'));
    }

    public function testCamel(): void
    {
        $this->assertSame('myPlugin', Str::camel('my_plugin'));
        $this->assertSame('myPlugin', Str::camel('my-plugin'));
    }

    public function testStudly(): void
    {
        $this->assertSame('MyPlugin', Str::studly('my_plugin'));
        $this->assertSame('MyPlugin', Str::studly('my-plugin'));
    }

    public function testStartsWith(): void
    {
        $this->assertTrue(Str::startsWith('hello world', 'hello'));
        $this->assertFalse(Str::startsWith('hello world', 'world'));
    }

    public function testEndsWith(): void
    {
        $this->assertTrue(Str::endsWith('hello world', 'world'));
        $this->assertFalse(Str::endsWith('hello world', 'hello'));
    }

    public function testContains(): void
    {
        $this->assertTrue(Str::contains('hello world', 'lo wo'));
        $this->assertFalse(Str::contains('hello world', 'xyz'));
    }

    public function testLimitTruncatesLongString(): void
    {
        $result = Str::limit('This is a very long string', 10);
        $this->assertSame('This is a ...', $result);
    }

    public function testLimitKeepsShortString(): void
    {
        $this->assertSame('short', Str::limit('short', 100));
    }

    public function testLimitCustomEnd(): void
    {
        $result = Str::limit('truncate me', 8, '…');
        $this->assertSame('truncate…', $result);
    }
}
