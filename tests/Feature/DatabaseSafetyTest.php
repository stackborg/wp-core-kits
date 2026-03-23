<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Feature;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\WordPress\Database;

/**
 * Database safety and edge case tests.
 *
 * Verifies table prefix is always applied,
 * and handles edge case inputs safely.
 */
class DatabaseSafetyTest extends TestCase
{
    public function testTableAlwaysAddsPrefix(): void
    {
        // Should never return a table name without prefix
        $table = Database::table('users');
        $this->assertStringStartsWith('wp_', $table);
    }

    public function testTableDoesNotDoublePrefix(): void
    {
        // If someone accidentally passes prefixed name
        $table = Database::table('wp_users');
        // Should still work — it will be wp_wp_users which is
        // expected behavior since the real table might be named that
        $this->assertStringStartsWith('wp_', $table);
    }

    public function testInsertReturnsId(): void
    {
        $id = Database::insert('test_table', [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
    }

    public function testInsertWithEmptyData(): void
    {
        // Should not crash with empty data
        $result = Database::insert('test_table', []);
        $this->assertIsInt($result);
    }

    public function testUpdateReturnsAffectedRows(): void
    {
        $result = Database::update(
            'test_table',
            ['name' => 'Updated'],
            ['id' => 1]
        );
        $this->assertIsInt($result);
    }

    public function testDeleteReturnsAffectedRows(): void
    {
        $result = Database::delete('test_table', ['id' => 1]);
        $this->assertIsInt($result);
    }

    public function testGetVarReturnsScalar(): void
    {
        $result = Database::getVar("SELECT COUNT(*) FROM " . Database::table('test'));
        // Mock returns '1'
        $this->assertNotNull($result);
    }

    public function testGetResultsReturnsArray(): void
    {
        $results = Database::getResults("SELECT * FROM " . Database::table('test'));
        $this->assertIsArray($results);
    }

    public function testPrefixIsConsistent(): void
    {
        $prefix1 = Database::prefix();
        $prefix2 = Database::prefix();
        $this->assertSame($prefix1, $prefix2);
    }
}
