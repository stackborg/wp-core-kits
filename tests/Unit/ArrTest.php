<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\Support\Arr;

class ArrTest extends TestCase
{
    public function testGetTopLevelKey(): void
    {
        $this->assertSame('bar', Arr::get(['foo' => 'bar'], 'foo'));
    }

    public function testGetNestedKey(): void
    {
        $data = ['db' => ['host' => 'localhost', 'port' => 3306]];
        $this->assertSame('localhost', Arr::get($data, 'db.host'));
        $this->assertSame(3306, Arr::get($data, 'db.port'));
    }

    public function testGetReturnsDefaultForMissing(): void
    {
        $this->assertSame('default', Arr::get([], 'missing', 'default'));
    }

    public function testGetDeeplyNested(): void
    {
        $data = ['a' => ['b' => ['c' => 'deep']]];
        $this->assertSame('deep', Arr::get($data, 'a.b.c'));
    }

    public function testSetTopLevel(): void
    {
        $data = [];
        Arr::set($data, 'key', 'value');
        $this->assertSame('value', $data['key']);
    }

    public function testSetNested(): void
    {
        $data = [];
        Arr::set($data, 'a.b.c', 'deep');
        $this->assertSame('deep', $data['a']['b']['c']);
    }

    public function testHasReturnsTrue(): void
    {
        $this->assertTrue(Arr::has(['foo' => 'bar'], 'foo'));
    }

    public function testHasReturnsFalse(): void
    {
        $this->assertFalse(Arr::has([], 'missing'));
    }

    public function testHasNested(): void
    {
        $data = ['a' => ['b' => 'value']];
        $this->assertTrue(Arr::has($data, 'a.b'));
        $this->assertFalse(Arr::has($data, 'a.c'));
    }

    public function testOnly(): void
    {
        $data = ['name' => 'John', 'email' => 'j@j.com', 'age' => 30];
        $result = Arr::only($data, ['name', 'email']);
        $this->assertSame(['name' => 'John', 'email' => 'j@j.com'], $result);
    }

    public function testExcept(): void
    {
        $data = ['name' => 'John', 'password' => 'secret', 'email' => 'j@j.com'];
        $result = Arr::except($data, ['password']);
        $this->assertSame(['name' => 'John', 'email' => 'j@j.com'], $result);
    }
}
