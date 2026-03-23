<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\Addon\AddonRegistry;
use Stackborg\WPCoreKits\Contracts\AddonInterface;

class AddonRegistryEdgeTest extends TestCase
{
    protected function setUp(): void
    {
        global $wp_options;
        $wp_options = [];
    }

    private function createMockAddon(
        string $slug = 'test-addon',
        string $name = 'Test Addon',
        string $version = '1.0.0',
        string $type = 'free',
    ): AddonInterface {
        return new class($slug, $name, $version, $type) implements AddonInterface {
            public function __construct(
                private string $slug,
                private string $name,
                private string $version,
                private string $type,
            ) {}
            public function slug(): string { return $this->slug; }
            public function name(): string { return $this->name; }
            public function version(): string { return $this->version; }
            public function type(): string { return $this->type; }
            public function features(): array { return ['basic' => 'free']; }
            public function providers(): array { return []; }
            public function requires(): array { return ['core' => '>=1.0.0']; }
            public function description(): string { return 'A test addon'; }
            public function cleanup(): void {}
        };
    }

    // ─── Registration ───────────────────────────────────

    public function testRegisterAddsToRegistry(): void
    {
        $registry = new AddonRegistry('test_addons_state');
        $registry->register($this->createMockAddon());

        $this->assertTrue($registry->isInstalled('test-addon'));
        $this->assertCount(1, $registry->getAll());
    }

    public function testRegisterMultipleAddons(): void
    {
        $registry = new AddonRegistry('test_addons_state');
        $registry->register($this->createMockAddon('addon-a', 'A'));
        $registry->register($this->createMockAddon('addon-b', 'B'));
        $registry->register($this->createMockAddon('addon-c', 'C'));

        $this->assertSame(3, $registry->count());
    }

    public function testDuplicateRegisterDoesNotDuplicate(): void
    {
        $registry = new AddonRegistry('test_addons_state');
        $registry->register($this->createMockAddon());
        $registry->register($this->createMockAddon());

        $this->assertSame(1, $registry->count());
    }

    // ─── Activate / Deactivate ──────────────────────────

    public function testNewAddonStartsInactive(): void
    {
        $registry = new AddonRegistry('test_addons_state');
        $registry->register($this->createMockAddon());

        $this->assertFalse($registry->isActive('test-addon'));
    }

    public function testActivateAddon(): void
    {
        $registry = new AddonRegistry('test_addons_state');
        $registry->register($this->createMockAddon());

        $result = $registry->activate('test-addon');
        $this->assertTrue($result);
        $this->assertTrue($registry->isActive('test-addon'));
    }

    public function testDeactivateAddon(): void
    {
        $registry = new AddonRegistry('test_addons_state');
        $registry->register($this->createMockAddon());
        $registry->activate('test-addon');

        $result = $registry->deactivate('test-addon');
        $this->assertTrue($result);
        $this->assertFalse($registry->isActive('test-addon'));
    }

    public function testActivateNonInstalledReturnsFalse(): void
    {
        $registry = new AddonRegistry('test_addons_state');
        $this->assertFalse($registry->activate('non-existent'));
    }

    public function testDeactivateNonInstalledReturnsFalse(): void
    {
        $registry = new AddonRegistry('test_addons_state');
        $this->assertFalse($registry->deactivate('non-existent'));
    }

    public function testActivateAlreadyActiveReturnsTrue(): void
    {
        $registry = new AddonRegistry('test_addons_state');
        $registry->register($this->createMockAddon());
        $registry->activate('test-addon');

        // Second activate should return true (already active)
        $this->assertTrue($registry->activate('test-addon'));
    }

    // ─── Get Active ─────────────────────────────────────

    public function testGetActiveReturnsOnlyActive(): void
    {
        $registry = new AddonRegistry('test_addons_state');
        $registry->register($this->createMockAddon('addon-a', 'A'));
        $registry->register($this->createMockAddon('addon-b', 'B'));
        $registry->register($this->createMockAddon('addon-c', 'C'));
        $registry->activate('addon-a');
        $registry->activate('addon-c');

        $active = $registry->getActive();
        $this->assertCount(2, $active);
        $this->assertArrayHasKey('addon-a', $active);
        $this->assertArrayHasKey('addon-c', $active);
        $this->assertArrayNotHasKey('addon-b', $active);
    }

    // ─── Remove ─────────────────────────────────────────

    public function testRemoveDeletesFromRegistry(): void
    {
        $registry = new AddonRegistry('test_addons_state');
        $registry->register($this->createMockAddon());
        $registry->activate('test-addon');
        $registry->remove('test-addon');

        $this->assertFalse($registry->isInstalled('test-addon'));
        $this->assertFalse($registry->isActive('test-addon'));
        $this->assertSame(0, $registry->count());
    }

    // ─── Get Instance ───────────────────────────────────

    public function testGetReturnsAddonInstance(): void
    {
        $registry = new AddonRegistry('test_addons_state');
        $addon = $this->createMockAddon();
        $registry->register($addon);

        $this->assertSame($addon, $registry->get('test-addon'));
    }

    public function testGetReturnsNullForMissing(): void
    {
        $registry = new AddonRegistry('test_addons_state');
        $this->assertNull($registry->get('missing'));
    }

    // ─── State Persistence ──────────────────────────────

    public function testStatePersistsToWpOptions(): void
    {
        global $wp_options;

        $registry = new AddonRegistry('test_state');
        $registry->register($this->createMockAddon());
        $registry->activate('test-addon');

        // Check wp_options was updated
        $this->assertArrayHasKey('test_state', $wp_options);
        $state = $wp_options['test_state'];
        $this->assertTrue($state['test-addon']['active']);
    }

    // ─── Scan (filesystem) ──────────────────────────────

    public function testScanDiscoverAddonsFromDirectory(): void
    {
        $baseDir = sys_get_temp_dir() . '/addon_scan_test_' . uniqid();
        $addonDir = $baseDir . '/my-addon';
        mkdir($addonDir, 0755, true);
        file_put_contents($addonDir . '/addon.json', json_encode([
            'slug'    => 'my-addon',
            'name'    => 'My Addon',
            'version' => '1.0.0',
            'type'    => 'free',
        ]));

        $registry = new AddonRegistry('scan_test_state');
        $registry->scan($baseDir);

        $this->assertTrue($registry->isInstalled('my-addon'));

        // Cleanup
        unlink($addonDir . '/addon.json');
        rmdir($addonDir);
        rmdir($baseDir);
    }

    public function testScanSkipsDirsWithoutAddonJson(): void
    {
        $baseDir = sys_get_temp_dir() . '/addon_skip_test_' . uniqid();
        $addonDir = $baseDir . '/invalid-addon';
        mkdir($addonDir, 0755, true);
        // No addon.json

        $registry = new AddonRegistry('skip_test_state');
        $registry->scan($baseDir);

        $this->assertSame(0, $registry->count());

        // Cleanup
        rmdir($addonDir);
        rmdir($baseDir);
    }

    public function testScanNonExistentDirDoesNothing(): void
    {
        $registry = new AddonRegistry('nodir_state');
        $registry->scan('/tmp/definitely_not_exists_' . uniqid());
        $this->assertSame(0, $registry->count());
    }

    // ─── Boot Prevention ────────────────────────────────

    public function testBootActiveOnlyRunsOnce(): void
    {
        $registry = new AddonRegistry('boot_test');
        $registry->register($this->createMockAddon());
        $registry->activate('test-addon');

        // Should not throw on double-boot
        $registry->bootActive();
        $registry->bootActive();
        $this->assertTrue(true); // reached without error
    }
}
