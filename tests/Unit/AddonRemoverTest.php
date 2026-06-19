<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\Addon\AddonRegistry;
use Stackborg\WPCoreKits\Addon\AddonRemover;
use Stackborg\WPCoreKits\Contracts\AddonInterface;

/**
 * Stub addon for testing cleanup behavior.
 */
class StubAddonForRemoval implements AddonInterface
{
    public bool $cleanupCalled = false;

    public function slug(): string { return 'test-addon'; }
    public function name(): string { return 'Test Addon'; }
    public function version(): string { return '1.0.0'; }
    public function type(): string { return 'free'; }
    public function features(): array { return []; }
    public function providers(): array { return []; }
    public function requires(): array { return []; }
    public function description(): string { return 'Test addon for removal'; }
    public function tables(): array { return []; }

    public function cleanup(): void
    {
        $this->cleanupCalled = true;
    }
}

/**
 * Stub addon whose cleanup() throws an exception.
 */
class StubAddonWithBrokenCleanup implements AddonInterface
{
    public function slug(): string { return 'broken-addon'; }
    public function name(): string { return 'Broken Addon'; }
    public function version(): string { return '1.0.0'; }
    public function type(): string { return 'free'; }
    public function features(): array { return []; }
    public function providers(): array { return []; }
    public function requires(): array { return []; }
    public function description(): string { return 'Addon with broken cleanup'; }
    public function tables(): array { return []; }

    public function cleanup(): void
    {
        throw new \RuntimeException('Cleanup failed intentionally');
    }
}

class AddonRemoverTest extends TestCase
{
    private string $addonsDir;

    protected function setUp(): void
    {
        $GLOBALS['wp_options'] = [];
        // Use a temp directory for addon files during tests
        $this->addonsDir = sys_get_temp_dir() . '/sb_test_addons_' . uniqid();
        mkdir($this->addonsDir, 0755, true);
    }

    protected function tearDown(): void
    {
        // Cleanup temp directory after each test
        if (is_dir($this->addonsDir)) {
            $this->removeDir($this->addonsDir);
        }
    }

    private function removeDir(string $dir): void
    {
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDir($path) : unlink($path);
        }
        rmdir($dir);
    }

    /** @test */
    public function itFailsWhenAddonIsNotInstalled(): void
    {
        $registry = new AddonRegistry('test_remover_state');
        $remover = new AddonRemover($registry, $this->addonsDir);

        $result = $remover->uninstall('nonexistent-addon');

        $this->assertFalse($result->success);
        $this->assertStringContainsString('not installed', $result->message);
    }

    /** @test */
    public function itSuccessfullyUninstallsInstalledAddon(): void
    {
        // Setup: register an addon in the registry
        $registry = new AddonRegistry('test_remover_state');
        $addon = new StubAddonForRemoval();
        $registry->register($addon);
        $registry->activate('test-addon');

        // Create a fake addon directory
        $addonDir = $this->addonsDir . '/test-addon';
        mkdir($addonDir, 0755, true);
        file_put_contents($addonDir . '/addon.php', '<?php // test');

        $remover = new AddonRemover($registry, $this->addonsDir);
        $result = $remover->uninstall('test-addon');

        $this->assertTrue($result->success);
        $this->assertStringContainsString('uninstalled', $result->message);
    }

    /** @test */
    public function itCallsCleanupOnAddonInstance(): void
    {
        $registry = new AddonRegistry('test_remover_state');
        $addon = new StubAddonForRemoval();
        $registry->register($addon);

        $remover = new AddonRemover($registry, $this->addonsDir);
        $remover->uninstall('test-addon');

        $this->assertTrue($addon->cleanupCalled);
    }

    /** @test */
    public function itContinuesUninstallEvenWhenCleanupThrows(): void
    {
        $registry = new AddonRegistry('test_remover_state');
        $addon = new StubAddonWithBrokenCleanup();
        $registry->register($addon);

        $remover = new AddonRemover($registry, $this->addonsDir);
        $result = $remover->uninstall('broken-addon');

        // Should succeed despite cleanup failure
        $this->assertTrue($result->success);
    }

    /** @test */
    public function itRemovesAddonFromRegistryState(): void
    {
        $registry = new AddonRegistry('test_remover_state');
        $addon = new StubAddonForRemoval();
        $registry->register($addon);

        $this->assertTrue($registry->isInstalled('test-addon'));

        $remover = new AddonRemover($registry, $this->addonsDir);
        $remover->uninstall('test-addon');

        $this->assertFalse($registry->isInstalled('test-addon'));
    }

    /** @test */
    public function itDeactivatesAddonBeforeRemoval(): void
    {
        $registry = new AddonRegistry('test_remover_state');
        $addon = new StubAddonForRemoval();
        $registry->register($addon);
        $registry->activate('test-addon');

        $this->assertTrue($registry->isActive('test-addon'));

        $remover = new AddonRemover($registry, $this->addonsDir);
        $remover->uninstall('test-addon');

        // After uninstall, addon should not exist in registry at all
        $this->assertFalse($registry->isInstalled('test-addon'));
    }

    /** @test */
    public function itDeletesAddonDirectory(): void
    {
        $registry = new AddonRegistry('test_remover_state');
        $addon = new StubAddonForRemoval();
        $registry->register($addon);

        // Create addon directory with files
        $addonDir = $this->addonsDir . '/test-addon';
        mkdir($addonDir, 0755, true);
        file_put_contents($addonDir . '/addon.php', '<?php // test');
        file_put_contents($addonDir . '/addon.json', '{}');

        $this->assertDirectoryExists($addonDir);

        $remover = new AddonRemover($registry, $this->addonsDir);
        $remover->uninstall('test-addon');

        $this->assertDirectoryDoesNotExist($addonDir);
    }

    /** @test */
    public function itHandsMissingDirectoryGracefully(): void
    {
        $registry = new AddonRegistry('test_remover_state');
        $addon = new StubAddonForRemoval();
        $registry->register($addon);

        // No directory created — should still succeed
        $remover = new AddonRemover($registry, $this->addonsDir);
        $result = $remover->uninstall('test-addon');

        $this->assertTrue($result->success);
    }
}
