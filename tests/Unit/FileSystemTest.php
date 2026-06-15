<?php

/**
 * Unit tests for Support\FileSystem utility.
 *
 * Tests the shared file system operations extracted
 * from AddonInstaller and AddonRemover.
 *
 * @package Stackborg\WPCoreKits\Tests\Unit
 */

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\Support\FileSystem;

class FileSystemTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/sb_fs_test_' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        // Clean up if test didn't remove it
        if (is_dir($this->tempDir)) {
            FileSystem::removeDirectory($this->tempDir);
        }
    }

    public function testRemoveEmptyDirectory(): void
    {
        $this->assertTrue(is_dir($this->tempDir));
        $result = FileSystem::removeDirectory($this->tempDir);
        $this->assertTrue($result);
        $this->assertFalse(is_dir($this->tempDir));
    }

    public function testRemoveDirectoryWithFiles(): void
    {
        file_put_contents($this->tempDir . '/file1.txt', 'content');
        file_put_contents($this->tempDir . '/file2.txt', 'content');

        $result = FileSystem::removeDirectory($this->tempDir);
        $this->assertTrue($result);
        $this->assertFalse(is_dir($this->tempDir));
    }

    public function testRemoveDirectoryWithNestedDirs(): void
    {
        mkdir($this->tempDir . '/sub1/sub2', 0755, true);
        file_put_contents($this->tempDir . '/sub1/sub2/deep.txt', 'nested');
        file_put_contents($this->tempDir . '/sub1/file.txt', 'content');

        $result = FileSystem::removeDirectory($this->tempDir);
        $this->assertTrue($result);
        $this->assertFalse(is_dir($this->tempDir));
    }

    public function testRemoveNonExistentDirectoryReturnsFalse(): void
    {
        $result = FileSystem::removeDirectory('/tmp/sb_nonexistent_' . uniqid());
        $this->assertFalse($result);
    }

    public function testRemoveDirectoryWithMixedContent(): void
    {
        mkdir($this->tempDir . '/assets/css', 0755, true);
        mkdir($this->tempDir . '/assets/js', 0755, true);
        file_put_contents($this->tempDir . '/index.php', '<?php // Silence');
        file_put_contents($this->tempDir . '/assets/css/style.css', 'body{}');
        file_put_contents($this->tempDir . '/assets/js/app.js', 'var x=1;');

        $result = FileSystem::removeDirectory($this->tempDir);
        $this->assertTrue($result);
        $this->assertFalse(is_dir($this->tempDir));
    }
}
