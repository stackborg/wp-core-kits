<?php

declare(strict_types=1);

namespace Stackborg\WPCoreKits\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Stackborg\WPCoreKits\Addon\AddonController;
use Stackborg\WPCoreKits\Addon\AddonInstaller;
use Stackborg\WPCoreKits\Addon\AddonRegistry;
use Stackborg\WPCoreKits\Addon\AddonRemover;
use Stackborg\WPCoreKits\Addon\AddonUpdater;
use Stackborg\WPCoreKits\Addon\FeatureManager;
use Stackborg\WPCoreKits\Addon\LicenseManager;
use Stackborg\WPCoreKits\Contracts\AddonInterface;

class AddonControllerTest extends TestCase
{
    private string $testDir;
    private string $addonsDir;
    private string $verifyKey = 'ctrl_test_key';
    private AddonRegistry $registry;
    private AddonInstaller $installer;
    private AddonRemover $remover;
    private AddonUpdater $updater;
    private LicenseManager $licenseManager;
    private FeatureManager $featureManager;
    private AddonController $controller;

    protected function setUp(): void
    {
        global $wp_options;
        $wp_options = [];

        $this->testDir = sys_get_temp_dir() . '/sb_ctrl_test_' . uniqid();
        $this->addonsDir = $this->testDir . '/addons';
        mkdir($this->addonsDir, 0755, true);

        $this->registry = new AddonRegistry('ctrl_test_state');
        $this->installer = new AddonInstaller($this->registry, $this->addonsDir, '1.0.0', '1.0.0');
        $this->remover = new AddonRemover($this->registry, $this->addonsDir);
        $this->updater = new AddonUpdater($this->registry, $this->installer, $this->addonsDir);
        $this->licenseManager = new LicenseManager('ctrl_test', $this->verifyKey);
        $this->featureManager = new FeatureManager($this->registry, $this->licenseManager);

        $this->controller = new AddonController(
            $this->registry,
            $this->installer,
            $this->remover,
            $this->updater,
            $this->licenseManager,
            $this->featureManager,
            '1.0.0',
            '1.0.0',
        );
    }

    protected function tearDown(): void
    {
        $this->cleanDir($this->testDir);
    }

    private function cleanDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }

    private function createMockAddon(string $slug, array $features = ['basic' => 'free']): AddonInterface
    {
        return new class($slug, $features) implements AddonInterface {
            public function __construct(private string $slug, private array $features) {}
            public function slug(): string { return $this->slug; }
            public function name(): string { return ucfirst($this->slug); }
            public function version(): string { return '1.0.0'; }
            public function type(): string { return 'freemium'; }
            public function features(): array { return $this->features; }
            public function providers(): array { return []; }
            public function requires(): array { return []; }
            public function description(): string { return 'Test addon'; }
            public function cleanup(): void {}
        };
    }

    private function mockRequest(array $params = []): \WP_REST_Request
    {
        return new \WP_REST_Request('', '', $params);
    }

    // ─── Routes Registration ────────────────────────────

    public function testRoutesAreDefined(): void
    {
        // Verify the controller can call routes() without error
        $this->controller->routes();
        $this->assertTrue(true); // Reached without error
    }

    // ─── Index ──────────────────────────────────────────

    public function testIndexReturnsEmptyWhenNoAddons(): void
    {
        $request = $this->mockRequest();
        $response = $this->controller->index($request);

        $data = $response->get_data();
        $this->assertSame(0, $data['count']);
        $this->assertEmpty($data['addons']);
    }

    public function testIndexReturnsRegisteredAddons(): void
    {
        $addon = $this->createMockAddon('templates', ['basic' => 'free', 'pro' => 'pro']);
        $this->registry->register($addon);
        $this->registry->activate('templates');

        $request = $this->mockRequest();
        $response = $this->controller->index($request);

        $data = $response->get_data();
        $this->assertSame(1, $data['count']);
        $this->assertSame('templates', $data['addons'][0]['slug']);
        $this->assertTrue($data['addons'][0]['active']);
        $this->assertSame('free', $data['addons'][0]['tier']);
        $this->assertArrayHasKey('features', $data['addons'][0]);
        $this->assertArrayHasKey('dependency_errors', $data['addons'][0]);
        $this->assertEmpty($data['addons'][0]['dependency_errors']);
    }

    // ─── Activate / Deactivate ──────────────────────────

    public function testActivateInstalledAddon(): void
    {
        $this->registry->register($this->createMockAddon('myext'));
        $request = $this->mockRequest(['slug' => 'myext']);

        $response = $this->controller->activate($request);
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertTrue($this->registry->isActive('myext'));
    }

    public function testActivateNonInstalledReturns404(): void
    {
        $request = $this->mockRequest(['slug' => 'ghost']);
        $response = $this->controller->activate($request);

        $this->assertSame(404, $response->get_status());
    }

    public function testDeactivateActiveAddon(): void
    {
        $this->registry->register($this->createMockAddon('myext'));
        $this->registry->activate('myext');
        $request = $this->mockRequest(['slug' => 'myext']);

        $response = $this->controller->deactivate($request);
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertFalse($this->registry->isActive('myext'));
    }

    public function testDeactivateNonInstalledReturns404(): void
    {
        $request = $this->mockRequest(['slug' => 'ghost']);
        $response = $this->controller->deactivate($request);

        $this->assertSame(404, $response->get_status());
    }

    // ─── Uninstall ──────────────────────────────────────

    public function testUninstallRegisteredAddon(): void
    {
        $this->registry->register($this->createMockAddon('removable'));
        $request = $this->mockRequest(['slug' => 'removable']);

        $response = $this->controller->uninstall($request);
        $data = $response->get_data();

        $this->assertTrue($data['success']);
        $this->assertFalse($this->registry->isInstalled('removable'));
    }

    public function testUninstallNonExistentReturns400(): void
    {
        $request = $this->mockRequest(['slug' => 'ghost']);
        $response = $this->controller->uninstall($request);

        $this->assertSame(400, $response->get_status());
    }

    // ─── Install without URL ────────────────────────────

    public function testInstallWithoutUrlAndWithoutApiReturns400(): void
    {
        $request = $this->mockRequest(['slug' => 'new-addon']);
        $response = $this->controller->install($request);

        $this->assertSame(400, $response->get_status());
    }

    // ─── License Deactivate ─────────────────────────────

    public function testDeactivateLicenseSucceeds(): void
    {
        $request = $this->mockRequest(['slug' => 'licensed']);
        $response = $this->controller->deactivateLicense($request);

        $data = $response->get_data();
        $this->assertTrue($data['success']);
    }

    // ─── License Activate without Key ───────────────────

    public function testActivateLicenseWithoutKeyReturns400(): void
    {
        $request = $this->mockRequest(['slug' => 'myext']);
        $response = $this->controller->activateLicense($request);

        $this->assertSame(400, $response->get_status());
    }

    // ─── Update without URL ─────────────────────────────

    public function testUpdateWithoutUrlReturns400(): void
    {
        $request = $this->mockRequest(['slug' => 'myext']);
        $response = $this->controller->update($request);

        $this->assertSame(400, $response->get_status());
    }
}
