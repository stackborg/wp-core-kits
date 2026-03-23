<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\WordPress\Database;

class DatabaseTest extends TestCase
{
    public function testPrefixReturnsWpPrefix(): void
    {
        $this->assertSame('wp_', Database::prefix());
    }

    public function testTableReturnsFullName(): void
    {
        $this->assertSame('wp_my_table', Database::table('my_table'));
    }

    public function testInsertReturnsInsertId(): void
    {
        $id = Database::insert('test_table', ['name' => 'John']);
        $this->assertIsInt($id);
    }

    public function testGetVarExecutesQuery(): void
    {
        $result = Database::getVar("SELECT 1");
        $this->assertNotNull($result);
    }

    public function testGetResultsReturnsArray(): void
    {
        $results = Database::getResults("SELECT 1");
        $this->assertIsArray($results);
    }

    public function testQueryExecutes(): void
    {
        $result = Database::query("SELECT 1");
        $this->assertNotFalse($result);
    }
}
